// Child theme custom scripts only.
// Parent theme core JS đã được enqueue trước qua handle `theme-js-bundle`.
import './components/stats-counter.js';
import './components/sticky-header.js';
import './components/footer-contact-form.js';
import './components/newsletter-form.js';
import './components/aos-global.js';
import './components/floating-contact.js';
import './components/share-popup.js';
import { initMobileMenu } from './components/mobile-menu.js';
import { initSearchPopup } from './components/search-popup.js';

const initHeaderInteractions = () => {
	initMobileMenu();
	initSearchPopup();
};

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initHeaderInteractions );
} else {
	initHeaderInteractions();
}
