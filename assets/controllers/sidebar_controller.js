import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['sidebar', 'main', 'toggle', 'mobileToggle', 'overlay'];

    connect() {
        this.handleResize = this.syncSidebarState.bind(this);
        window.addEventListener('resize', this.handleResize);

        this.isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        this.syncSidebarState();
    }

    disconnect() {
        window.removeEventListener('resize', this.handleResize);
    }

    toggle(event) {
        event.preventDefault();
        this.isCollapsed = !this.isCollapsed;
        localStorage.setItem('sidebarCollapsed', this.isCollapsed);
        this.syncSidebarState();
    }

    toggleMobile(event) {
        event && event.preventDefault();
        if (!this.hasSidebarTarget || !this.hasOverlayTarget) return;

        this.sidebarTarget.classList.toggle('mobile-open');
        this.overlayTarget.classList.toggle('active');
    }

    syncSidebarState() {
        if (!this.hasSidebarTarget || !this.hasMainTarget) return;

        if (this.isCollapsed) {
            this.sidebarTarget.classList.add('collapsed');
            this.mainTarget.classList.add('expanded');
        } else {
            this.sidebarTarget.classList.remove('collapsed');
            this.mainTarget.classList.remove('expanded');
        }
    }
}