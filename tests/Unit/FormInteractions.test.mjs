import assert from 'node:assert/strict';
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { basename, join, resolve, sep } from 'node:path';
import { spawn } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { test } from 'node:test';

const root = fileURLToPath(new URL('../../', import.meta.url));
const browser = ['C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'].find(existsSync);
const enabled = process.env.AHC_FORM_BROWSER_FIXTURE === '1' && !!browser;

async function withBrowser(name, callback) {
    const fixture = JSON.parse(readFileSync(join(root, 'storage/app/private/form-interaction-' + name + '.json'), 'utf8'));
    const directory = mkdtempSync(join(tmpdir(), 'ahc-form-browser-'));
    let child;
    let socket;
    let command;
    let closed = false;
    try {
        const html = fixture.html.replace(/<link[^>]*https:\/\/fonts\.bunny\.net[^>]*>/g, '')
            .replace(/https?:\/\/[^/]+\/(build|images)\//g, (_, path) => pathToFileURL(join(root, 'public', path)).href + '/')
            .replace(/wire:poll[^\s=>]*(?:="[^"]*")?/g, '');
        const page = join(directory, 'fixture.html');
        writeFileSync(page, html);
        child = spawn(browser, ['--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
            '--disable-background-networking', '--disable-component-update', '--disable-sync', '--disable-extensions',
            '--allow-file-access-from-files', '--disable-renderer-backgrounding', '--disable-background-timer-throttling',
            '--user-data-dir=' + join(directory, 'profile'), '--remote-debugging-port=0', 'about:blank'], { stdio: 'ignore', windowsHide: true });
        const deadline = Date.now() + 20000;
        const portFile = join(directory, 'profile/DevToolsActivePort');
        let port;
        while (!port) {
            try {
                const candidate = readFileSync(portFile, 'utf8').split(/\r?\n/)[0];
                if (/^[0-9]+$/.test(candidate)) port = candidate;
            } catch (error) {
                if (!['ENOENT', 'EBUSY', 'EPERM'].includes(error.code)) throw error;
            }
            assert.ok(Date.now() < deadline, 'Browser did not start');
            if (!port) await new Promise(resolve => setTimeout(resolve, 50));
        }
        const pages = await (await fetch('http://127.0.0.1:' + port + '/json/list')).json();
        socket = new WebSocket(pages.find(page => page.type === 'page').webSocketDebuggerUrl);
        await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
        const pending = new Map();
        let sequence = 0;
        socket.onmessage = event => {
            const message = JSON.parse(event.data);
            pending.get(message.id)?.(message);
        };
        command = (method, params = {}) => new Promise((resolve, reject) => {
            const id = ++sequence;
            const timeout = setTimeout(() => { pending.delete(id); reject(new Error(method + ' timed out')); }, 15000);
            pending.set(id, message => {
                clearTimeout(timeout);
                pending.delete(id);
                message.error ? reject(new Error(message.error.message)) : resolve(message.result);
            });
            socket.send(JSON.stringify({ id, method, params }));
        });
        const evaluate = async expression => {
            const result = await command('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
            assert.ok(!result.exceptionDetails, JSON.stringify(result.exceptionDetails));
            return result.result.value;
        };
        await command('Page.enable');
        await command('Runtime.enable');
        await command('Page.addScriptToEvaluateOnNewDocument', { source: `
            window.__errors = [];
            window.__requests = [];
            window.__responses = ${JSON.stringify(fixture.responses)};
            window.__done = 0;
            addEventListener('error', e => __errors.push(e.message));
            addEventListener('unhandledrejection', e => __errors.push(String(e.reason)));
            window.confirm = () => true;
            window.alert = () => {};
            window.fetch = async (url, options) => {
                const name = window.__nextReply;
                if (!name || !__responses[name]) throw new Error('Unexpected browser request: ' + url);
                window.__nextReply = null;
                __requests.push(JSON.parse(options.body));
                return new Response(JSON.stringify({ components: [__responses[name]], assets: [] }), { status: 200, headers: { 'Content-Type': 'application/json' } });
            };
        ` });
        await command('Emulation.setDeviceMetricsOverride', { width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false });
        await command('Page.navigate', { url: pathToFileURL(page).href });
        const wait = async (expression, message) => {
            const deadline = Date.now() + 6000;
            while (!await evaluate(expression)) {
                assert.ok(Date.now() < deadline, message + ': ' + await evaluate('JSON.stringify(window.__errors)'));
                await new Promise(resolve => setTimeout(resolve, 30));
            }
        };
        await wait('!!window.Alpine && !!window.Livewire && !document.querySelector("[x-cloak]")', 'Alpine did not initialize');
        await evaluate(`Livewire.hook('commit', ({succeed}) => succeed(() => window.__done++));
            window.D = title => [...document.querySelectorAll('details')].find(el => el.querySelector('summary')?.textContent.trim() === title);
            window.C = (group, value) => [...document.querySelectorAll('input[type=checkbox]')].find(el => el.getAttribute('wire:model') === 'physical_examination.' + group + '.findings' && el.value === value);
            window.input = (id, value) => { const el = document.getElementById(id); el.focus(); el.value = value; el.dispatchEvent(new Event('input', {bubbles:true})); };
            window.choose = (id, value) => { const el = document.getElementById(id); el.value = value; el.dispatchEvent(new Event('change', {bubbles:true})); };
        `);
        const check = async (expression, message) => assert.equal(await evaluate(expression), true, message + ': ' + await evaluate('JSON.stringify(window.__errors)'));
        const reply = async (name, action) => {
            const completed = await evaluate('window.__done');
            await evaluate('window.__nextReply = ' + JSON.stringify(name) + '; ' + action);
            await wait('window.__done > ' + completed, 'Livewire response did not complete: ' + name);
            await evaluate('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
        };
        await callback({ evaluate, check, reply, wait });
        assert.deepEqual(await evaluate('window.__errors'), [], 'Browser console must contain no runtime errors');
        await command('Browser.close');
        closed = true;
    } finally {
        if (!closed && command && socket?.readyState === WebSocket.OPEN) {
            try { await command('Browser.close'); } catch {}
        }
        socket?.close();
        if (child && child.exitCode === null) {
            await new Promise(resolve => {
                const timeout = setTimeout(() => { child.kill(); resolve(); }, 3000);
                child.once('exit', () => { clearTimeout(timeout); resolve(); });
            });
        }
        const target = resolve(directory);
        assert.ok(target.startsWith(resolve(tmpdir()) + sep) && basename(target).startsWith('ahc-form-browser-'));
        rmSync(target, { recursive: true, force: true, maxRetries: 20, retryDelay: 100 });
    }
}

test('consultation checkboxes, searchable multi-selects, validation and drafts survive actual Livewire morphs', { skip: !enabled }, async () => {
    await withBrowser('consultation', async ({ evaluate, check, reply }) => {
        await evaluate(`D('Physical Examination').querySelector('summary').click();
            D('Chief Complaint and Medical History').querySelector('summary').click();
            C('skin', 'Pallor').click();`);
        await check(`C('skin','Pallor').checked && !C('skin','Rashes').checked && !C('skin','Jaundice').checked && !C('skin','Good Skin Turgor').checked`, 'Only Pallor may be checked');
        await evaluate(`C('skin','Rashes').click(); C('skin','Pallor').click();
            C('heent','Nasal Discharge').click(); C('chest_lungs','Wheezes').click(); C('heart','Murmurs').click();
            C('abdomen','Tenderness').click(); C('extremities','Normal Gait').click();
            input('chief_complaint','Interaction test complaint'); input('clinical-subjective_notes','Retained narrative');
            input('clinical-other_pe_findings','Additional examination notes');`);
        await check(`document.querySelectorAll('input[type=checkbox]:checked').length === 6 && !C('skin','Pallor').checked && C('skin','Rashes').checked`, 'Independent findings and unchecking must be isolated');
        await reply('searchF', `input('symptom-search','F')`);
        await check(`D('Physical Examination').open && !D('Chief Complaint and Medical History').open && document.querySelector('#symptom-search').getAttribute('aria-expanded') === 'true'`, 'Section and dropdown state must survive search');
        await reply('selectF', `[...document.querySelectorAll('#symptom-options button')].find(el=>el.textContent.trim()==='Fever').click()`);
        await check(`document.querySelector('#symptom-search').value === 'F' && document.querySelector('#symptom-search').getAttribute('aria-expanded') === 'true'`, 'Selecting a symptom must preserve search and open state');
        await reply('searchC', `input('symptom-search','C')`);
        await reply('selectC', `[...document.querySelectorAll('#symptom-options button')].find(el=>el.textContent.trim()==='Cough').click()`);
        await check(`!!document.querySelector('[aria-label="Remove Fever"]') && !!document.querySelector('[aria-label="Remove Cough"]')`, 'Multiple symptoms must remain selected');
        await reply('removeF', `document.querySelector('[aria-label="Remove Fever"]').click()`);
        await check(`!document.querySelector('[aria-label="Remove Fever"]') && !!document.querySelector('[aria-label="Remove Cough"]')`, 'Removing one symptom must retain the other');
        await evaluate(`document.querySelector('#symptom-search').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}))`);
        await check(`document.querySelector('#symptom-search').getAttribute('aria-expanded') === 'false' && D('Subjective Assessment and Symptoms (S)').open`, 'Escape must close only the picker');
        await evaluate(`document.querySelector('#symptom-search').click();
            window.__enter = new KeyboardEvent('keydown', {key:'Enter',bubbles:true,cancelable:true});
            document.querySelector('#symptom-search').dispatchEvent(window.__enter);`);
        await check(`document.querySelector('#symptom-search').getAttribute('aria-expanded') === 'true' && __enter.defaultPrevented`, 'Click must reopen the picker and Enter must not submit the form');
        await evaluate(`D('Assessment and Diagnosis (A)').querySelector('summary').click()`);
        await reply('diagnosisSearch', `input('diagnosis-search','not catalog')`);
        await check(`D('Assessment and Diagnosis (A)').open && document.querySelector('#diagnosis-search').getAttribute('aria-expanded') === 'true'`, 'Searching diagnoses must retain the picker and parent section');
        await reply('diagnosisOther', `[...document.querySelectorAll('#diagnosis-options button')].find(el=>el.textContent.trim()==='Other / New Diagnosis').click()`);
        await check(`D('Assessment and Diagnosis (A)').open && document.querySelector('#diagnosis-search').getAttribute('aria-expanded') === 'false'`, 'A single diagnosis selection must close only its picker');
        await evaluate(`D('Assessment and Diagnosis (A)').querySelector('summary').click()`);
        await reply('invalid', `document.querySelector('form[aria-label] button[type=submit]').click()`);
        await check(`D('Assessment and Diagnosis (A)').open && D('Plan and Follow-up (P)').open && D('Physical Examination').open && document.getElementById('clinical-subjective_notes').value === 'Retained narrative' && C('skin','Rashes').checked`, 'Validation must reveal affected panels and retain input');
        await reply('draft', `[...document.querySelectorAll('button')].find(el=>el.textContent.trim()==='Save Draft').click()`);
        await check(`document.body.textContent.includes('Draft saved.') && D('Physical Examination').open && document.querySelectorAll('input[type=checkbox]:checked').length === 6`, 'Draft saving must retain all selections and open panels');
    });
});

test('saved consultation draft loads only the selected examination findings', { skip: !enabled }, async () => {
    await withBrowser('resumed', async ({ check }) => {
        await check(`C('skin','Rashes').checked && !C('skin','Pallor').checked && !C('skin','Jaundice').checked && !C('skin','Good Skin Turgor').checked && document.querySelectorAll('input[type=checkbox]:checked').length === 6`, 'Reloaded findings must match saved individual selections');
    });
});

test('profile dropdowns, checkbox groups and dose removal retain the correct controls', { skip: !enabled }, async () => {
    await withBrowser('profile', async ({ evaluate, check, reply }) => {
        await evaluate(`D('Personal Information').querySelector('summary').click()`);
        await reply('married', `choose('profile-civil_status','Married')`);
        await check(`D('Personal Information').open && document.getElementById('profile-civil_status').value === 'Married'`, 'Profile select must not collapse its section');
        await evaluate(`D('Family and Membership Information').querySelector('summary').click()`);
        await reply('fourPs', `choose('profile-four_ps','Yes')`);
        await check(`!!document.getElementById('profile-four_ps_number') && D('Family and Membership Information').open && document.getElementById('profile-civil_status').value === 'Married'`, 'Conditional fields must not reset neighboring dropdowns');
        await evaluate(`D('Non-Communicable Diseases (NCD)').querySelector('summary').click()`);
        await reply('asthma', `document.querySelector('input[value="Asthma"]').click()`);
        await check(`D('Non-Communicable Diseases (NCD)').open && document.querySelector('input[value="Asthma"]').checked && !document.querySelector('input[value="Diabetes"]').checked`, 'NCD selection must remain independent and expanded');
        await evaluate(`[...document.querySelectorAll('details')].find(el=>el.querySelector('summary')?.textContent.includes('new doses')).querySelector('summary').click()`);
        await reply('addFirst', `[...document.querySelectorAll('button')].find(el=>el.textContent.trim()==='Add vaccine / supplement dose').click()`);
        await reply('addSecond', `[...document.querySelectorAll('button')].find(el=>el.textContent.trim()==='Add vaccine / supplement dose').click()`);
        await reply('firstVaccine', `choose('newDoses-0-vaccine_name','IPV')`);
        await reply('secondVaccine', `choose('newDoses-1-vaccine_name','OPV')`);
        await evaluate(`window.__retainedRow = document.getElementById('newDoses-1-vaccine_name').closest('fieldset')`);
        await reply('removeFirst', `[...document.querySelectorAll('button')].find(el=>el.textContent.trim()==='Remove unsaved dose').click()`);
        await check(`document.getElementById('newDoses-0-vaccine_name').value === 'OPV' && document.getElementById('newDoses-0-vaccine_name').closest('fieldset') === window.__retainedRow && !document.getElementById('newDoses-1-vaccine_name')`, 'Removing an earlier dose must preserve the remaining row identity and selection');
    });
});

test('syndrome checkbox arrays select and deselect independently', { skip: !enabled }, async () => {
    await withBrowser('syndromes', async ({ evaluate, check }) => {
        await evaluate(`window.__symptoms = [...document.querySelectorAll('input[type=checkbox]')].filter(el=>el.getAttribute('wire:model')==='symptomIds'); __symptoms[0].click()`);
        await check(`__symptoms[0].checked && __symptoms.slice(1).every(el=>!el.checked)`, 'Only one syndrome mapping may be selected');
        await evaluate(`__symptoms[1].click(); __symptoms[0].click()`);
        await check(`!__symptoms[0].checked && __symptoms[1].checked && __symptoms.slice(2).every(el=>!el.checked)`, 'Deselecting one syndrome mapping must preserve the other');
    });
});

test('sensitivity radios remain exclusive and the review checkbox is independent', { skip: !enabled }, async () => {
    await withBrowser('settings', async ({ evaluate, check }) => {
        await evaluate(`document.querySelector('input[type=radio][value=high]').click(); document.querySelector('input[type=checkbox]').click()`);
        await check(`document.querySelectorAll('input[type=radio]:checked').length === 1 && document.querySelector('input[value=high]').checked && document.querySelector('input[type=checkbox]').checked`, 'Radio choices must be exclusive');
    });
});

test('dropdowns inside modals retain search and multiple choices and close through intended actions', { skip: !enabled }, async () => {
    await withBrowser('modal', async ({ evaluate, check }) => {
        await evaluate(`document.getElementById('multi-trigger').click(); input('modal-search','query'); document.getElementById('modal-first').click(); document.getElementById('modal-second').click()`);
        await check(`Alpine.$data(document.getElementById('multi-trigger')).open && Alpine.$data(document.getElementById('modal-first')).show && document.getElementById('modal-first').checked && document.getElementById('modal-second').checked && document.getElementById('modal-search').value === 'query'`, 'Typing and selecting must leave the dropdown and modal open');
        await evaluate(`document.getElementById('modal-first').click(); document.getElementById('modal-search').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}))`);
        await check(`!Alpine.$data(document.getElementById('multi-trigger')).open && Alpine.$data(document.getElementById('modal-first')).show && !document.getElementById('modal-first').checked && document.getElementById('modal-second').checked`, 'Escape must close only the nested dropdown and preserve other selections');
        await evaluate(`document.getElementById('multi-trigger').click(); document.getElementById('modal-outside').click()`);
        await check(`!Alpine.$data(document.getElementById('multi-trigger')).open && Alpine.$data(document.getElementById('modal-first')).show`, 'Outside clicks within the modal must close only the dropdown');
        await evaluate(`document.getElementById('modal-outside').dispatchEvent(new KeyboardEvent('keydown',{key:'Escape',bubbles:true}))`);
        await check(`!Alpine.$data(document.getElementById('modal-first')).show && __requests.length === 0`, 'Escape outside the dropdown must close the modal without submitting');
    });
});
