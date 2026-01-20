/**
 * Teacher Dashboard loader
 * - Checks if current staff has an active class assignment
 * - Loads class teacher dashboard fragment if assigned
 * - Otherwise loads subject teacher dashboard fragment
 */
(function () {
  async function loadFragment(path) {
    try {
      const res = await fetch(path, { credentials: "same-origin" });
      if (!res.ok) throw new Error("Failed to load fragment: " + res.status);
      const html = await res.text();
      const container = document.getElementById("teacher-dashboard-fragment");
      container.innerHTML = html;

      // Execute any scripts in the loaded fragment (both external and inline)
      const scripts = container.querySelectorAll("script");
      for (const oldScript of scripts) {
        const newScript = document.createElement("script");
        // copy attributes
        for (const attr of oldScript.attributes) {
          newScript.setAttribute(attr.name, attr.value);
        }
        if (oldScript.src) {
          // external script - append and wait for load
          await new Promise((resolve, reject) => {
            newScript.onload = resolve;
            newScript.onerror = reject;
            document.body.appendChild(newScript);
          });
        } else {
          // inline script
          newScript.textContent = oldScript.textContent;
          document.body.appendChild(newScript);
        }
        // Remove the old script tag from fragment to avoid duplicates
        oldScript.parentNode && oldScript.parentNode.removeChild(oldScript);
      }
    } catch (err) {
      console.error(err);
      document.getElementById("teacher-dashboard-fragment").innerHTML =
        '<div class="alert alert-danger">Failed to load dashboard fragment.</div>';
    }
  }

  async function init() {
    try {
      const user = AuthContext.getUser();
      if (!user) {
        console.warn("No user in AuthContext");
        await loadFragment(
          "/Kingsway/components/dashboards/subject_teacher_dashboard.php"
        );
        return;
      }

      // Try to get staff profile for current user
      let staffProfile = null;
      try {
        staffProfile = await API.staff.getProfile();
      } catch (e) {
        console.warn("Failed to get staff profile:", e);
      }

      if (staffProfile && staffProfile.id) {
        // Get current assignments for this staff
        try {
          const assignmentsResp = await API.staff.getCurrentAssignments(
            staffProfile.id
          );
          // API may return { assignments: [...], count } or raw array
          const assignments = Array.isArray(assignmentsResp)
            ? assignmentsResp
            : assignmentsResp?.assignments || assignmentsResp?.data || [];

          // Determine if teacher should be treated as class teacher.
          // Rule: any assignment to a class whose school level is Lower Primary (grades 1-3)
          // or explicit role 'class_teacher' qualifies.
          const classAssignments = (assignments || []).filter(
            (a) => a.class_id || a.class_id === 0
          );

          let isClassTeacher = false;

          // Quick check: any explicit class_teacher role
          if (
            assignments.some(
              (a) => a.role === "class_teacher" || a.role === "class"
            )
          ) {
            isClassTeacher = true;
          } else if (classAssignments.length > 0) {
            // Fetch class details for unique class_ids to inspect level
            const uniqueClassIds = [
              ...new Set(
                classAssignments.map((a) => a.class_id).filter(Boolean)
              ),
            ];
            try {
              const classFetches = uniqueClassIds.map((cid) =>
                API.academic.getClass(cid)
              );
              const classResults = await Promise.all(classFetches);
              for (const cr of classResults) {
                const cls = cr?.data || cr || null;
                if (!cls) continue;
                // cls may be wrapped or raw; normalize
                const levelId =
                  cls.level_id || (cls.level && cls.level.id) || null;
                const levelCode =
                  cls.code || (cls.level && cls.level.code) || null;
                const name = cls.name || cls.class_name || "";
                // Lower Primary is represented by level_id = 2 or code 'LP' in this system
                if (levelId === 2 || String(levelCode).toUpperCase() === "LP") {
                  isClassTeacher = true;
                  break;
                }
                // Fallback: if class name contains 'Grade 1'..'Grade 3' or 'Playgroup' treat as class teacher
                if (/Grade\s*(1|2|3)|Playgroup|PP/i.test(name)) {
                  isClassTeacher = true;
                  break;
                }
              }
            } catch (e) {
              console.warn("Failed to fetch class details for assignments", e);
            }
          }

          if (isClassTeacher) {
            await loadFragment(
              "/Kingsway/components/dashboards/class_teacher_dashboard.php"
            );
            return;
          }
        } catch (e) {
          console.warn("Failed to get assignments:", e);
        }
      }

      // Default: load subject teacher dashboard
      await loadFragment(
        "/Kingsway/components/dashboards/subject_teacher_dashboard.php"
      );
    } finally {
      const loader = document.getElementById("teacher-dashboard-loading");
      if (loader) loader.style.display = "none";
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    init();
  });
})();
