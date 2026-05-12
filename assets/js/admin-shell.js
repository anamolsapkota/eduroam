(function () {
    var shell = document.getElementById('adminShell');
    var sidebar = document.getElementById('adminSidebar');
    var toggle = document.getElementById('sidebarToggle');
    var backdrop = document.getElementById('sidebarBackdrop');
    var clockEl = document.getElementById('adminClock');
    var STORAGE_KEY = 'eduroam-sidebar';
    var MOBILE_BREAKPOINT = 768;

    if (!shell || !toggle) return;

    function isMobile() {
        return window.innerWidth < MOBILE_BREAKPOINT;
    }

    function restoreState() {
        if (isMobile()) return;
        var saved = localStorage.getItem(STORAGE_KEY);
        if (saved === 'collapsed') {
            shell.classList.add('sidebar-collapsed');
        } else {
            shell.classList.remove('sidebar-collapsed');
        }
    }

    toggle.addEventListener('click', function () {
        if (isMobile()) {
            shell.classList.toggle('sidebar-mobile-open');
        } else {
            shell.classList.toggle('sidebar-collapsed');
            var isCollapsed = shell.classList.contains('sidebar-collapsed');
            localStorage.setItem(STORAGE_KEY, isCollapsed ? 'collapsed' : 'expanded');
        }
    });

    if (backdrop) {
        backdrop.addEventListener('click', function () {
            shell.classList.remove('sidebar-mobile-open');
        });
    }

    if (sidebar) {
        sidebar.addEventListener('click', function (e) {
            var link = e.target.closest('.sidebar-nav-item');
            if (link && isMobile()) {
                shell.classList.remove('sidebar-mobile-open');
            }
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && shell.classList.contains('sidebar-mobile-open')) {
            shell.classList.remove('sidebar-mobile-open');
        }
    });

    window.addEventListener('resize', function () {
        if (!isMobile()) {
            shell.classList.remove('sidebar-mobile-open');
            restoreState();
        }
    });

    function updateClock() {
        if (!clockEl) return;
        try {
            var now = new Date();
            var formatted = now.toLocaleTimeString('en-US', {
                timeZone: 'Asia/Kathmandu',
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
            clockEl.textContent = formatted + ' NPT';
        } catch (e) {
            clockEl.textContent = new Date().toLocaleTimeString() + ' NPT';
        }
    }

    updateClock();
    setInterval(updateClock, 1000);
    restoreState();
})();
