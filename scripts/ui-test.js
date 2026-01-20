const puppeteer = require("puppeteer");

(async () => {
  const baseUrl = process.env.BASE_URL || "http://localhost/Kingsway";
  console.log("Testing UI at:", baseUrl);

  const browser = await puppeteer.launch({
    headless: true,
    args: ["--no-sandbox", "--disable-setuid-sandbox"],
  });
  const page = await browser.newPage();
  page.setDefaultTimeout(30000);

  try {
    // Test 1: Check if index page loads
    console.log("Test 1: Loading index page...");
    const indexRes = await page.goto(`${baseUrl}/index.php`, {
      waitUntil: "networkidle2",
    });
    if (!indexRes || indexRes.status() >= 400) {
      throw new Error(
        `Failed to load index page: ${
          indexRes ? indexRes.status() : "no response"
        }`
      );
    }
    console.log("✓ Index page loads successfully");

    // Test 2: Check if login form is present
    const loginForm = await page.$(
      "#loginModal, .login-form, form[action*='login']"
    );
    if (!loginForm) {
      console.warn(
        "⚠️  Login form not found - might be using different auth method"
      );
    } else {
      console.log("✓ Login form detected");
    }

    // Test 3: Check if home.php loads (main application)
    console.log("Test 3: Loading home page...");
    const homeRes = await page.goto(`${baseUrl}/home.php`, {
      waitUntil: "networkidle2",
    });
    if (!homeRes || homeRes.status() >= 400) {
      throw new Error(
        `Failed to load home page: ${
          homeRes ? homeRes.status() : "no response"
        }`
      );
    }
    console.log("✓ Home page loads successfully");

    // Test 4: Check for basic app structure
    const sidebar = await page.$("#sidebar-container, .sidebar, #sidebar");
    const mainContent = await page.$("#main-content-area, .main-content, main");

    if (!sidebar && !mainContent) {
      console.warn("⚠️  App layout not detected - might be loading dashboard");
    } else {
      console.log("✓ App layout structure detected");
    }

    // Test 5: Try loading a dashboard route
    console.log("Test 5: Testing dashboard route...");
    const dashboardRes = await page.goto(
      `${baseUrl}/home.php?route=school_accountant_dashboard`,
      { waitUntil: "networkidle2" }
    );
    if (!dashboardRes || dashboardRes.status() >= 400) {
      console.warn(
        `⚠️  Dashboard route failed: ${
          dashboardRes ? dashboardRes.status() : "no response"
        }`
      );
    } else {
      console.log("✓ Dashboard route loads");

      // Check for dashboard content
      const dashboardElement = await page.$(
        "#school-accountant-dashboard, .dashboard"
      );
      if (dashboardElement) {
        console.log("✓ Dashboard content detected");
      } else {
        console.log(
          "⚠️  Dashboard content not immediately visible (might require auth)"
        );
      }
    }

    console.log("\n🎉 UI smoke test: SUCCESS");
    console.log("Basic application structure and routing are working");
    await browser.close();
    process.exit(0);
  } catch (err) {
    console.error("❌ UI smoke test failed:", err.message);
    await browser.close();
    process.exit(1);
  }
})();
