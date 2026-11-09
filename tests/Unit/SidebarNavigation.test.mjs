import assert from 'node:assert/strict';
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { basename, join, resolve, sep } from 'node:path';
import { spawn } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/m, '');

function createGroup({ active = false, collapsed = false, desktop = true } = {}) {
    const definitions = new Map();
    const focused = [];
    runInNewContext(source, {
        Alpine: { data: (name, factory) => definitions.set(name, factory), start() {} },
        Livewire: { start() {} },
        window: { innerWidth: desktop ? 1440 : 390 },
    });
    const group = Object.assign(definitions.get('sidebarGroup')(), {
        collapsed,
        isDesktop: desktop,
        $el: { dataset: { active: String(active) } },
        $refs: {
            toggle: { focus: () => focused.push('toggle') },
            children: { querySelector: () => ({ focus: () => focused.push('first child') }) },
        },
        $nextTick: (callback) => callback(),
    });
    group.init();

    return { group, focused };
}

test('active pages open their group while unrelated groups start closed', () => {
    assert.equal(createGroup({ active: true }).group.open, true);
    assert.equal(createGroup().group.open, false);
});

test('disclosure clicks open and close a group on desktop and mobile', () => {
    for (const desktop of [true, false]) {
        const { group } = createGroup({ desktop });
        group.toggleGroup();
        assert.equal(group.open, true);
        group.toggleGroup();
        assert.equal(group.open, false);
    }
});

test('a collapsed desktop sidebar expands when its group icon is activated', () => {
    const { group } = createGroup({ collapsed: true });
    group.toggleGroup();
    assert.equal(group.collapsed, false);
    assert.equal(group.open, true);
});

test('mobile group interactions preserve the saved desktop collapse preference', () => {
    const { group } = createGroup({ collapsed: true, desktop: false });
    group.expandGroup();
    assert.equal(group.open, true);
    assert.equal(group.collapsed, true);
});

test('keyboard expansion focuses the first child and closing returns focus to the header', () => {
    const { group, focused } = createGroup();
    group.expandGroup(true);
    assert.equal(group.open, true);
    assert.deepEqual(focused, ['first child']);
    group.closeGroup();
    assert.equal(group.open, false);
    assert.deepEqual(focused, ['first child', 'toggle']);
});

test('Livewire navigation opens the new active group and closes an inactive group', () => {
    const { group } = createGroup();
    group.$el.dataset.active = 'true';
    group.syncActiveGroup();
    assert.equal(group.open, true);
    group.$el.dataset.active = 'false';
    group.syncActiveGroup();
    assert.equal(group.open, false);
    group.toggleGroup();
    assert.equal(group.open, true);
});

const browser = [
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
].find(existsSync);
const browserEnabled = process.env.AHC_SIDEBAR_BROWSER_FIXTURE === '1' && browser;
const projectRoot = fileURLToPath(new URL('../../', import.meta.url));

async function checkRenderedSidebar() {
    let step = 'initialization';
    const errors = [];
    window.addEventListener('error', (event) => { if (event.message) errors.push(event.message); });
    window.addEventListener('unhandledrejection', (event) => errors.push(String(event.reason)));
    const check = (condition, message) => { if (!condition) throw new Error(message); };
    const waitFor = async (condition) => {
        const deadline = performance.now() + 2500;
        while (!condition()) {
            if (performance.now() > deadline) throw new Error('Timed out waiting for sidebar state');
            await new Promise((resolve) => setTimeout(resolve, 10));
        }
    };
    const aside = document.querySelector('#app-sidebar');
    const patient = document.querySelector('#patient-management-toggle');
    const medical = document.querySelector('#medical-toggle');
    const children = document.querySelector('#patient-management-children');
    const visible = (element) => getComputedStyle(element).display !== 'none';
    const mobile = window.innerWidth < 1024;
    document.addEventListener('click', (event) => {
        if (event.target.closest('a')) event.preventDefault();
    }, { capture: true });

    try {
        await waitFor(() => window.Alpine && !aside.hasAttribute('x-cloak') && !children.hasAttribute('x-cloak'));
        check(patient.getAttribute('aria-expanded') === 'true', 'Active patient group must start expanded');
        check(medical.getAttribute('aria-expanded') === 'false', 'Unrelated medical group must start closed');
        check(document.querySelector('[aria-label="Patient Registration"]').getAttribute('aria-current') === 'page', 'Registration must be highlighted');
        if (mobile) {
            check(aside.inert, 'Closed mobile drawer must be inert');
            document.querySelector('[aria-label="Open navigation menu"]').click();
            await waitFor(() => !aside.inert);
        }
        step = 'close patient group';
        patient.click();
        await waitFor(() => patient.getAttribute('aria-expanded') === 'false' && !visible(children));
        step = 'open patient group';
        patient.click();
        await waitFor(() => patient.getAttribute('aria-expanded') === 'true' && visible(children));
        step = 'keyboard focus child';
        patient.focus();
        patient.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }));
        await waitFor(() => document.activeElement.getAttribute('aria-label') === 'Patient Record');
        step = 'keyboard close group';
        document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft', bubbles: true }));
        await waitFor(() => document.activeElement === patient && patient.getAttribute('aria-expanded') === 'false' && !visible(children));
        patient.click();
        await waitFor(() => patient.getAttribute('aria-expanded') === 'true' && visible(children));

        step = 'drawer and desktop collapse';
        if (mobile) {
            document.querySelector('[aria-label="Patient Record"]').click();
            await waitFor(() => aside.inert);
            document.querySelector('[aria-label="Open navigation menu"]').click();
            await waitFor(() => !aside.inert);
            document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
            await waitFor(() => aside.inert);
            document.querySelector('[aria-label="Open navigation menu"]').click();
            await waitFor(() => !aside.inert);
        } else {
            step = 'collapse desktop sidebar';
            document.querySelector('[data-sidebar-collapse]').click();
            await waitFor(() => aside.getBoundingClientRect().width < 100 && !visible(children));
            step = 'reopen desktop sidebar through group';
            patient.click();
            await waitFor(() => aside.getBoundingClientRect().width > 200 && visible(children));
        }

        step = 'navigation event';
        document.querySelector('[data-sidebar-group="patient-management"]').dataset.active = 'false';
        document.querySelector('[data-sidebar-group="medical"]').dataset.active = 'true';
        document.dispatchEvent(new Event('livewire:navigated', { bubbles: true }));
        await waitFor(() => medical.getAttribute('aria-expanded') === 'true' && patient.getAttribute('aria-expanded') === 'false');
        medical.click();
        await waitFor(() => medical.getAttribute('aria-expanded') === 'false');
        medical.click();
        await waitFor(() => medical.getAttribute('aria-expanded') === 'true');
        const navigation = document.querySelector('[aria-label="Main navigation"]');
        check(navigation.scrollWidth <= navigation.clientWidth, 'Navigation must fit the sidebar without horizontal overflow');
        check(errors.length === 0, errors.join('; '));
        document.documentElement.dataset.sidebarBrowserResult = btoa(JSON.stringify({ passed: true, mobile, width: innerWidth }));
    } catch (error) {
        document.documentElement.dataset.sidebarBrowserResult = btoa(JSON.stringify({ passed: false, step, error: error.message, errors,
            width: innerWidth, expanded: patient.getAttribute('aria-expanded'), display: getComputedStyle(children).display,
            open: window.Alpine?.$data(patient).open, collapsed: window.Alpine?.$data(patient).collapsed, desktop: window.Alpine?.$data(patient).isDesktop }));
    }
}

for (const width of [1440, 390]) {
    test(`rendered sidebar interactions at ${width}px in a real headless browser`, { skip: !browserEnabled }, async () => {
        const profile = mkdtempSync(join(tmpdir(), 'ahc-sidebar-'));
        let browserProcess;
        let socket;
        try {
            let html = readFileSync(join(projectRoot, 'storage/app/private/sidebar-browser-fixture.html'), 'utf8')
                .replace(/<main id="main-content"[\s\S]*?<\/main>/, '<main id="main-content">Sidebar interaction fixture</main>')
                .replace(/<link[^>]*https:\/\/fonts\.bunny\.net[^>]*>/g, '')
                .replace(/https?:\/\/[^/]+\/(build|images)\//g, (_, directory) => pathToFileURL(join(projectRoot, 'public', directory)).href + '/');
            const fixture = join(profile, 'sidebar.html');
            writeFileSync(fixture, html);
            browserProcess = spawn(browser, [
                '--headless=new', '--disable-gpu', '--no-sandbox', '--no-first-run', '--no-default-browser-check',
                '--disable-background-networking', '--disable-component-update', '--disable-sync',
                '--allow-file-access-from-files', '--disable-extensions', '--disable-default-apps',
                '--disable-renderer-backgrounding', '--disable-background-timer-throttling',
                `--user-data-dir=${join(profile, 'profile')}`, `--window-size=${width},900`,
                '--remote-debugging-port=0', 'about:blank',
            ], { stdio: 'ignore', windowsHide: true });
            const portFile = join(profile, 'profile/DevToolsActivePort');
            const deadline = Date.now() + 15000;
            while (!existsSync(portFile)) {
                assert.ok(Date.now() < deadline, 'Browser debugging endpoint did not start');
                await new Promise((resolve) => setTimeout(resolve, 50));
            }
            const port = readFileSync(portFile, 'utf8').split(/\r?\n/)[0];
            const pages = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
            socket = new WebSocket(pages.find((page) => page.type === 'page').webSocketDebuggerUrl);
            await new Promise((resolve, reject) => { socket.onopen = resolve; socket.onerror = reject; });
            let sequence = 0;
            const pending = new Map();
            socket.onmessage = (event) => {
                const message = JSON.parse(event.data);
                const callback = pending.get(message.id);
                if (callback) {
                    pending.delete(message.id);
                    callback(message);
                }
            };
            const command = (method, params = {}) => new Promise((resolve, reject) => {
                const id = ++sequence;
                const timeout = setTimeout(() => { pending.delete(id); reject(new Error(`Browser command timed out: ${method}`)); }, 15000);
                pending.set(id, (message) => {
                    clearTimeout(timeout);
                    if (message.error) reject(new Error(message.error.message));
                    else resolve(message.result);
                });
                socket.send(JSON.stringify({ id, method, params }));
            });
            await command('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
            await command('Page.navigate', { url: pathToFileURL(fixture).href });
            await command('Page.bringToFront');
            let loaded = false;
            while (!loaded) {
                assert.ok(Date.now() < deadline + 15000, 'Browser fixture did not initialize');
                const state = await command('Runtime.evaluate', { expression: '!!window.Alpine && !!document.querySelector("#medical-toggle")', returnByValue: true });
                loaded = state.result.value;
                if (!loaded) await new Promise((resolve) => setTimeout(resolve, 50));
            }
            const evaluation = await command('Runtime.evaluate', { expression: `(${checkRenderedSidebar.toString()})().then(() => document.documentElement.dataset.sidebarBrowserResult)`, awaitPromise: true, returnByValue: true });
            assert.ok(evaluation.result.value, JSON.stringify(evaluation));
            const report = JSON.parse(Buffer.from(evaluation.result.value, 'base64').toString());
            assert.equal(report.passed, true, JSON.stringify(report));
            assert.equal(report.mobile, width < 1024);
            await command('Browser.close');
        } finally {
            socket?.close();
            if (browserProcess && browserProcess.exitCode === null) {
                await new Promise((resolve) => {
                    const timeout = setTimeout(() => { browserProcess.kill(); resolve(); }, 3000);
                    browserProcess.once('exit', () => { clearTimeout(timeout); resolve(); });
                });
            }
            const target = resolve(profile);
            assert.ok(target.startsWith(resolve(tmpdir()) + sep) && basename(target).startsWith('ahc-sidebar-'));
            rmSync(target, { recursive: true, force: true, maxRetries: 5, retryDelay: 100 });
        }
    });
}
