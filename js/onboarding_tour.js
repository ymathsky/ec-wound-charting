// Onboarding Tour using Driver.js
// Documentation: https://driverjs.com/

document.addEventListener('DOMContentLoaded', function() {
    // Check if Driver is loaded
    if (typeof window.driver === 'undefined') {
        console.warn('Driver.js not loaded');
        return;
    }

    const driver = window.driver.js.driver;

    const tourDriver = driver({
        showProgress: true,
        steps: [
            { 
                element: '#sidebar-nav', 
                popover: { 
                    title: 'Navigation', 
                    description: 'Use the sidebar to navigate between Patients, Schedule, and Reports.', 
                    side: "right", 
                    align: 'start' 
                } 
            },
            { 
                element: '#search-bar-container', 
                popover: { 
                    title: 'Quick Search', 
                    description: 'Quickly find patients by name or MRN here.', 
                    side: "bottom", 
                    align: 'start' 
                } 
            },
            { 
                element: '#user-menu-btn', 
                popover: { 
                    title: 'User Profile', 
                    description: 'Manage your account settings and logout here.', 
                    side: "left", 
                    align: 'start' 
                } 
            },
            { 
                element: '#help-menu-btn', 
                popover: { 
                    title: 'Help & Support', 
                    description: 'Access the User Manual or restart this tour anytime.', 
                    side: "left", 
                    align: 'start' 
                } 
            }
        ]
    });

    // Expose start function globally
    window.startOnboardingTour = function() {
        tourDriver.drive();
    };

    // Auto-start for new users (simulated with localStorage)
    if (!localStorage.getItem('ec_tour_completed')) {
        // Optional: Uncomment to auto-start
        // tourDriver.drive();
        // localStorage.setItem('ec_tour_completed', 'true');
    }
});
