const puppeteer = require("puppeteer");

(async () => {
  const baseUrl = process.env.BASE_URL || "http://localhost/Kingsway";
  const pagePath = `${baseUrl}/components/dashboards/school_accountant_dashboard.php`;
  const browser = await puppeteer.launch({ headless: true });
  const page = await browser.newPage();
  page.setDefaultTimeout(10000);

  try {
    console.log("Visiting", pagePath);
    const res = await page.goto(pagePath, { waitUntil: "networkidle2" });
    if (!res || res.status() >= 400) {
      throw new Error(
        "Failed to load page: " + (res ? res.status() : "no response")
      );
    }

    // Wait for main dashboard element
    await page.waitForSelector("#school-accountant-dashboard");

    // Check KPIs
    const kpis = [
      "kpi_fees_due",
      "kpi_collected",
      "kpi_outstanding",
      "kpi_unreconciled",
      "kpi_avg_payment_amount",
      "kpi_reconciliation_rate",
    ];

    for (const id of kpis) {
      const exists = (await page.$(`#${id}`)) !== null;
      console.log(`${id}: ${exists ? "present" : "MISSING"}`);
      if (!exists) throw new Error(`Missing KPI element: ${id}`);
    }

    // Quick actions contain data-route attributes
    const quickAction = await page.$(".dashboard-action[data-route]");
    if (!quickAction) throw new Error("No quick action with data-route found");
    const route = await page.evaluate(
      (el) => el.getAttribute("data-route"),
      quickAction
    );
    console.log("Quick action route:", route);

    // Wait for dynamic cards to render (summary cards container or card links)
    await page
      .waitForSelector("#summaryCardsContainer, .card-link", { timeout: 5000 })
      .catch(() => {});

    // Check that avg/reconciliation cards have data-route via created cards
    const avgCard = await page.$(
      '.card-link[data-route="school_accountant_payments"]'
    );
    const recCard = await page.$(
      '.card-link[data-route="school_accountant_unmatched_payments"]'
    );

    console.log("Avg card route present:", !!avgCard);
    console.log("Reconciliation card route present:", !!recCard);

    if (!avgCard) throw new Error("Avg Payment card route not found");
    if (!recCard) throw new Error("Reconciliation card route not found");

    // Click a quick action and observe navigation (falls back to page navigation)
    await quickAction.click();
    await page.waitForTimeout(500);
    const url = page.url();
    console.log("URL after click:", url);

    console.log("UI smoke test: SUCCESS");
    await browser.close();
    process.exit(0);
  } catch (err) {
    console.error("UI smoke test failed:", err);
    await browser.close();
    process.exit(2);
  }
})();
