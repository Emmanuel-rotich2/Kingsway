// Simulate the API response structure and test the allowed set building logic
const apiResponse = [
    {
        id: 100,
        label: "Dashboard",
        icon: "fas fa-tachometer-alt",
        url: "director_owner_dashboard",
        subitems: []
    },
    {
        id: 200,
        label: "Finance",
        icon: "fas fa-coins",
        url: "manage_finance",
        subitems: [
            {
                id: 201,
                label: "Financial Reports",
                url: "finance_reports",
                subitems: []
            },
            {
                id: 202,
                label: "Budget Overview",
                url: "budget_overview",
                subitems: []
            },
            {
                id: 203,
                label: "Approvals",
                url: "finance_approvals",
                subitems: []
            }
        ]
    },
    {
        id: 250,
        label: "Students",
        icon: "fas fa-user-graduate",
        url: "manage_students",
        subitems: [
            {
                id: 251,
                label: "All Students",
                url: "manage_students",
                subitems: []
            },
            {
                id: 252,
                label: "Admissions",
                url: "manage_students_admissions",
                subitems: []
            },
            {
                id: 1062,
                label: "Discipline",
                url: "student_discipline",
                subitems: []
            }
        ]
    }
];

// Simulate the home.php route validation logic
const allowed = new Set();
const stack = Array.isArray(apiResponse) ? apiResponse.slice() : (apiResponse?.data || []);

console.log("Building allowed set from API response:");
console.log("Stack length:", stack.length);

let itemsProcessed = 0;
while (stack.length) {
    const it = stack.pop();
    itemsProcessed++;
    console.log(`Processing item ${itemsProcessed}:`, it);
    
    if (it?.url) {
        console.log(`  Adding URL to allowed set: "${it.url}"`);
        allowed.add(String(it.url));
    }
    
    if (Array.isArray(it?.subitems)) {
        console.log(`  Has ${it.subitems.length} subitems, adding to stack`);
        for (const sub of it.subitems) {
            stack.push(sub);
        }
    }
}

console.log("\nFinal allowed set:");
console.log([...allowed].sort());

// Test the routes
const testRoutes = [
    "director_owner_dashboard",
    "manage_finance",
    "budget_overview",  // This is the problematic one
    "student_discipline"
];

console.log("\nRoute validation results:");
testRoutes.forEach(route => {
    const allowed_has = allowed.has(route);
    console.log(`Route "${route}": ${allowed_has ? "✓ ALLOWED" : "✗ BLOCKED"}`);
});
