const { test, expect } = require('@playwright/test');

const viewports = [
  { name: 'desktop', width: 1280, height: 800 },
  { name: 'tablet', width: 768, height: 1024 },
  { name: 'mobile', width: 390, height: 844 },
];

const roles = [
  { role: 'super_admin', email: 'admin@edusphere.com', password: 'Admin@123', home: '/dashboard.php', pages: ['/admin/schools.php', '/students/index.php', '/search.php?q=Second'] },
  { role: 'school_admin', email: 'schooladmin@sch001.com', password: 'School@123', home: '/school-admin/dashboard.php', pages: ['/students/index.php', '/fees/invoices.php', '/reports/index.php'] },
  { role: 'admin', email: 'admin@sch001.com', password: 'Admin@123', home: '/dashboard.php', pages: ['/students/index.php', '/attendance/index.php', '/admin/users.php'] },
  { role: 'teacher', email: 'teacher@sch001.com', password: 'Teacher@123', home: '/dashboard.php', pages: ['/attendance/mark.php', '/grades/index.php', '/tasks/index.php'] },
  { role: 'student', email: 'student@sch001.com', password: 'Student@123', home: '/dashboard.php', pages: ['/grades/my.php', '/fees/my.php', '/attendance/my.php'] },
  { role: 'parent', email: 'parent@sch001.com', password: 'Parent@123', home: '/parent/dashboard.php', pages: ['/parent/fees.php', '/parent/grades.php', '/parent/attendance.php'] },
];

async function login(page, email, password) {
  await page.goto('/auth/login.php');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('domcontentloaded');
  await expect(page).not.toHaveURL(/auth\/login\.php/);
}

async function expectTenantSafe(page, path) {
  const response = await page.goto(path);
  expect(response, path).not.toBeNull();
  expect(response.status(), path).toBeLessThan(400);
  await expect(page).not.toHaveURL(/auth\/login\.php/);
  const body = await page.locator('body').innerText();
  expect(body, path).not.toMatch(/Fatal error|Uncaught/);
  expect(body, path).not.toContain('Second School Student');
  expect(body, path).not.toContain('Second School Course');
  expect(body, path).not.toContain('Second School Event');
}

for (const account of roles) {
  test(`${account.role} portal on desktop, tablet, and mobile`, async ({ page }) => {
    await login(page, account.email, account.password);
    for (const viewport of viewports) {
      await page.setViewportSize({ width: viewport.width, height: viewport.height });
      await expectTenantSafe(page, account.home);
      for (const path of account.pages) {
        await expectTenantSafe(page, path);
      }
    }
  });
}
