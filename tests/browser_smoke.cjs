const { chromium } = require('playwright');

const base = (process.env.ACADEMICS_BASE || 'http://127.0.0.1:8090').replace(/\/$/, '');
const personas = [
  {
    name: 'platform super admin',
    email: 'admin@edusphere.com',
    password: 'Admin@123',
    routes: [
      '/dashboard.php', '/admin/users.php', '/admin/settings.php',
      '/admin/schools.php', '/admin/subscriptions.php', '/admin/messages.php',
      '/reports/index.php'
    ]
  },
  {
    name: 'school admin',
    email: 'schooladmin@sch001.com',
    password: 'School@123',
    routes: [
      '/dashboard.php', '/students/index.php', '/teachers/index.php',
      '/student-services/manage.php', '/learning/index.php', '/courses/index.php', '/tasks/index.php', '/attendance/index.php',
      '/fees/index.php', '/fees/invoices.php', '/leave/index.php',
      '/notes/index.php', '/events/index.php', '/schedule/index.php',
      '/expenses/index.php', '/reports/index.php'
    ]
  },
  {
    name: 'teacher',
    email: 'teacher@sch001.com',
    password: 'Teacher@123',
    routes: [
      '/dashboard.php', '/students/index.php', '/courses/index.php',
      '/learning/index.php', '/tasks/index.php', '/attendance/my.php', '/grades/index.php',
      '/notes/index.php', '/events/index.php', '/schedule/index.php',
      '/expenses/index.php', '/reports/index.php'
    ]
  },
  {
    name: 'student',
    email: 'student@sch001.com',
    password: 'Student@123',
    routes: [
      '/dashboard.php', '/students/show.php?id=2', '/students/photo.php', '/tasks/index.php',
      '/courses/index.php', '/courses/my.php', '/attendance/my.php',
      '/grades/my.php', '/fees/my.php', '/notes/index.php',
      '/student-services/index.php', '/learning/index.php', '/student-life/index.php?section=eproject', '/student-life/index.php?section=cv', '/student-life/index.php?section=achievement', '/reports/index.php'
    ]
  },
  {
    name: 'parent',
    email: 'parent@sch001.com',
    password: 'Parent@123',
    routes: [
      '/parent/dashboard.php', '/parent/children.php', '/parent/grades.php',
      '/parent/fees.php', '/parent/attendance.php', '/parent/progress.php',
      '/parents/messages.php', '/notes/index.php'
    ]
  }
];

(async () => {
  const browser = await chromium.launch({ headless: true });
  const failures = [];
  let checks = 0;

  try {
    for (const persona of personas) {
      const context = await browser.newContext({
        viewport: { width: 1440, height: 900 },
        ignoreHTTPSErrors: true
      });
      const page = await context.newPage();
      const pageErrors = [];
      page.on('pageerror', error => pageErrors.push(error.message));

      await page.goto(base + '/auth/login.php', { waitUntil: 'domcontentloaded' });
      const emailField = page.locator('input[name="email"]');
      const passwordField = page.locator('input[name="password"]');
      await emailField.fill(persona.email);
      await passwordField.fill(persona.password);
      await page.locator('button[type="submit"], input[type="submit"]').first().click();
      await page.waitForLoadState('domcontentloaded');
      await page.waitForTimeout(150);
      if (new URL(page.url()).pathname.endsWith('/auth/login.php')) {
        failures.push(persona.name + ': login did not establish an authenticated session');
        await context.close();
        continue;
      }
      checks++;

      const internalLinks = new Set();
      for (const route of persona.routes) {
        const response = await page.goto(base + route, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(80);
        const finalPath = new URL(page.url()).pathname;
        const status = response ? response.status() : 0;
        const body = await page.locator('body').innerText().catch(() => '');
        const serverError = /Fatal error:|Uncaught (?:PDOException|Error|TypeError)|SQLSTATE\[[A-Z0-9]+\]/i.test(body);
        const hrefs = await page.locator('a[href]').evaluateAll(anchors => anchors.map(a => a.href).filter(Boolean));
        for (const href of hrefs) {
          if (href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:')) continue;
          try {
            const target = new URL(href, base);
            if (target.origin !== new URL(base).origin || /logout\.php$/i.test(target.pathname)) continue;
            if (/\.(?:png|jpe?g|gif|svg|css|js|pdf|ico|woff2?)$/i.test(target.pathname)) continue;
            internalLinks.add(target.toString());
          } catch (_) {
            failures.push(persona.name + ' ' + route + ': malformed link ' + href);
          }
        }

        if (status >= 500 || status === 403 || finalPath.endsWith('/auth/login.php') || serverError) {
          failures.push(persona.name + ' ' + route + ': HTTP ' + status + ', final ' + finalPath);
        } else {
          checks++;
        }
      }

      for (const href of internalLinks) {
        try {
          const response = await context.request.get(href, { timeout: 10000 });
          const finalUrl = new URL(response.url());
          if (response.status() === 404 || response.status() >= 500
              || finalUrl.pathname.endsWith('/auth/login.php')) {
            failures.push(persona.name + ' broken internal link: ' + href
              + ' -> HTTP ' + response.status() + ' ' + response.url());
          } else {
            checks++;
          }
        } catch (error) {
          failures.push(persona.name + ' internal link request failed: ' + href + ' (' + error.message + ')');
        }
      }

      for (const width of [360, 390, 768, 1024, 1440]) {
        await page.setViewportSize({ width, height: 900 });
        const route = persona.name === 'parent' ? '/parent/dashboard.php' : '/dashboard.php';
        await page.goto(base + route, { waitUntil: 'domcontentloaded' });
        const dimensions = await page.evaluate(() => ({
          viewport: window.innerWidth,
          document: document.documentElement.scrollWidth,
          body: document.body.scrollWidth
        }));
        if (dimensions.document > dimensions.viewport + 2 || dimensions.body > dimensions.viewport + 2) {
          failures.push(persona.name + ' responsive ' + width + 'px: document='
            + dimensions.document + ', body=' + dimensions.body);
        } else {
          checks++;
        }
      }

      if (pageErrors.length) {
        failures.push(persona.name + ' browser runtime errors: ' + pageErrors.join(' | '));
      }
      await context.close();
      console.log('PASS browser persona: ' + persona.name);
    }
  } finally {
    await browser.close();
  }

  console.log('Browser checks: ' + checks);
  console.log('Browser failures: ' + failures.length);
  if (failures.length) {
    for (const failure of failures) console.error('FAIL ' + failure);
    process.exit(1);
  }
  console.log('ALL BROWSER CHECKS PASSED');
})();
