// DASHBOARD BUTTON TESTING SCRIPT
// Copy and paste this into your browser console (F12) when on the dashboard

console.log("🧪 TESTING DASHBOARD BUTTONS AND FUNCTIONALITY");
console.log("================================================");

// 1. Test if controller exists
if (typeof schoolAccountantDashboardController !== "undefined") {
  console.log("✅ Dashboard controller found");

  // 2. Run built-in tests
  console.log("\n📊 Running built-in feature tests:");
  try {
    const results = schoolAccountantDashboardController.runFeatureTests();
    console.log(`Results: ${results.passed} passed, ${results.failed} failed`);
  } catch (error) {
    console.error("Test suite failed:", error);
  }

  // 3. Test buttons directly
  console.log("\n🔘 Testing individual buttons:");
  const testResults = schoolAccountantDashboardController.testAllButtons();

  // 4. Check UI elements
  console.log("\n🖼️  Checking UI elements:");
  const uiElements = [
    "chartExportPng",
    "chartExportCsv",
    "tableExportCsv",
    "tableExportExcel",
    "chartDateRange",
    "chartShowComparison",
    "applyTransactionFilters",
    "clearTransactionFilters",
    "configureAlerts",
  ];

  uiElements.forEach((id) => {
    const el = document.getElementById(id);
    if (el) {
      console.log(`✅ ${id} - Found (${el.tagName.toLowerCase()})`);
    } else {
      console.log(`❌ ${id} - Missing`);
    }
  });

  // 5. Force setup if needed
  if (testResults.working < testResults.found) {
    console.log("\n🔧 Some buttons missing listeners, running force setup...");
    schoolAccountantDashboardController.forceSetupButtons();
  }

  // 6. Show dashboard status
  console.log("\n📈 Dashboard Status:");
  schoolAccountantDashboardController.showDashboardStatus();
} else {
  console.error("❌ schoolAccountantDashboardController not found!");
  console.log("Make sure you're on the correct dashboard page");
}

console.log("\n✅ Testing complete! Check results above.");
console.log("If buttons still don't work, try: location.reload()");
