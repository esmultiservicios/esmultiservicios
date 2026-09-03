(() => {
    const toggle = document.querySelector('[data-nav-toggle]');
    const nav = document.querySelector('[data-nav]');
    const navLinks = nav ? [...nav.querySelectorAll('[data-nav-link]')] : [];
    const indicator = nav ? nav.querySelector('[data-nav-indicator]') : null;

    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const open = nav.classList.toggle('open');
            toggle.setAttribute('aria-expanded', String(open));
            document.body.classList.toggle('nav-open', open);
        });

        navLinks.forEach((link) => {
            link.addEventListener('click', () => {
                nav.classList.remove('open');
                toggle.setAttribute('aria-expanded', 'false');
                document.body.classList.remove('nav-open');
            });
        });
    }

    const moveIndicator = (link) => {
        if (!nav || !indicator || !link || window.innerWidth <= 980) return;
        const navRect = nav.getBoundingClientRect();
        const linkRect = link.getBoundingClientRect();
        indicator.style.width = `${Math.max(28, linkRect.width - 20)}px`;
        indicator.style.transform = `translateX(${linkRect.left - navRect.left + 10}px)`;
        nav.classList.add('has-active');
    };

    const setActiveLink = (sectionId) => {
        let active = null;
        navLinks.forEach((link) => {
            const isActive = link.dataset.section === sectionId;
            link.classList.toggle('is-active', isActive);
            if (isActive) active = link;
        });
        if (active) moveIndicator(active);
    };

    const sections = navLinks
        .map((link) => document.getElementById(link.dataset.section || ''))
        .filter(Boolean);

    const updateActiveFromScroll = () => {
        if (!sections.length) return;
        const header = document.querySelector('.site-header');
        const offset = (header ? header.offsetHeight : 0) + Math.min(180, window.innerHeight * 0.24);
        let current = sections[0];

        sections.forEach((section) => {
            if (section.getBoundingClientRect().top <= offset) {
                current = section;
            }
        });

        setActiveLink(current.id);
    };

    navLinks.forEach((link) => {
        link.addEventListener('click', () => {
            const sectionId = link.dataset.section || '';
            if (sectionId) setActiveLink(sectionId);
        });
    });

    const initialSection = location.hash ? location.hash.slice(1) : 'home';
    setActiveLink(initialSection);
    updateActiveFromScroll();

    let scrollTicking = false;
    window.addEventListener('scroll', () => {
        if (scrollTicking) return;
        scrollTicking = true;
        window.requestAnimationFrame(() => {
            updateActiveFromScroll();
            scrollTicking = false;
        });
    }, { passive: true });

    window.addEventListener('resize', () => {
        updateActiveFromScroll();
        const active = nav ? nav.querySelector('[data-nav-link].is-active') : null;
        moveIndicator(active);
    }, { passive: true });

    const revealVisibleItems = () => {
        document.querySelectorAll('.reveal:not(.visible)').forEach((element) => {
            if (element.getBoundingClientRect().top < window.innerHeight - 60) {
                element.classList.add('visible');
            }
        });
    };

    revealVisibleItems();
    window.addEventListener('scroll', revealVisibleItems, { passive: true });

    const contactForm = document.querySelector('[data-contact-form]');
    const contactStatus = document.querySelector('[data-contact-status]');
    if (contactForm && contactStatus) {
        contactForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = contactForm.querySelector('button[type="submit"]');
            const original = button ? button.textContent : '';
            contactStatus.className = 'contact-form-status';
            contactStatus.textContent = document.documentElement.lang === 'es' ? 'Enviando…' : 'Sending…';
            if (button) button.disabled = true;
            try {
                const response = await fetch(contactForm.action, {
                    method: 'POST',
                    body: new FormData(contactForm),
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await response.json();
                if (!response.ok || !data.ok) throw new Error(data.message || 'Request failed');
                contactStatus.classList.add('success');
                contactStatus.textContent = document.documentElement.lang === 'es'
                    ? 'Gracias. Recibimos tu consulta.'
                    : 'Thank you. We received your inquiry.';
                contactForm.reset();
            } catch (error) {
                contactStatus.classList.add('error');
                contactStatus.textContent = error.message || (document.documentElement.lang === 'es'
                    ? 'No pudimos enviar la consulta.'
                    : 'We could not send the inquiry.');
            } finally {
                if (button) {
                    button.disabled = false;
                    button.textContent = original;
                }
            }
        });
    }
})();

/* Public image preview: inspect real website images without opening another page. */
(() => {
    const selectors = [
        '.hero-media .product-window img',
        '.product-showcase-section .experience-media img',
        '.responsive-phone-frame img',
        '.cami-login-preview img',
        '.projects-grid .project-card > img',
        '.company-artwork-card > img'
    ];

    const images = [...document.querySelectorAll(selectors.join(','))];
    if (!images.length) return;

    const modal = document.createElement('div');
    modal.className = 'site-image-modal';
    modal.setAttribute('aria-hidden', 'true');
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-label', document.documentElement.lang === 'es' ? 'Vista ampliada de imagen' : 'Expanded image preview');
    modal.innerHTML = `
        <div class="site-image-dialog">
            <div class="site-image-toolbar">
                <span class="site-image-toolbar-title">${document.documentElement.lang === 'es' ? 'Vista ampliada' : 'Expanded preview'}</span>
                <button type="button" class="site-image-close" aria-label="${document.documentElement.lang === 'es' ? 'Cerrar vista ampliada' : 'Close expanded preview'}">
                    <span class="site-image-close-symbol" aria-hidden="true">×</span>
                    <span class="site-image-close-text">${document.documentElement.lang === 'es' ? 'Cerrar' : 'Close'}</span>
                </button>
            </div>
            <div class="site-image-stage">
                <img src="" alt="">
            </div>
        </div>`;
    document.body.appendChild(modal);

    const modalImage = modal.querySelector('.site-image-stage img');
    const closeButton = modal.querySelector('.site-image-close');
    let lastTrigger = null;

    const close = () => {
        modal.classList.remove('open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('site-modal-open');
        modalImage.removeAttribute('src');
        if (lastTrigger) lastTrigger.focus({ preventScroll: true });
    };

    const open = (image, trigger) => {
        if (!image || !image.currentSrc && !image.src) return;
        lastTrigger = trigger || image;
        modalImage.src = image.currentSrc || image.src;
        modalImage.alt = image.alt || (document.documentElement.lang === 'es' ? 'Vista ampliada' : 'Expanded preview');
        modal.classList.add('open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('site-modal-open');
        window.requestAnimationFrame(() => closeButton.focus({ preventScroll: true }));
    };

    const zoomIcon = `
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <circle cx="11" cy="11" r="6"></circle>
            <path d="m16 16 4 4"></path>
            <path d="M11 8v6M8 11h6"></path>
        </svg>`;

    images.forEach((image) => {
        image.classList.add('site-preview-ready');
        image.tabIndex = 0;
        image.setAttribute('role', 'button');
        image.setAttribute('aria-label', `${document.documentElement.lang === 'es' ? 'Ampliar imagen' : 'Enlarge image'}: ${image.alt || ''}`.trim());

        const host = image.parentElement;
        if (host) {
            host.classList.add('site-preview-host');
            if (!host.querySelector(':scope > .site-zoom-button')) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'site-zoom-button';
                button.innerHTML = zoomIcon;
                button.setAttribute('aria-label', `${document.documentElement.lang === 'es' ? 'Ampliar imagen' : 'Enlarge image'}: ${image.alt || ''}`.trim());
                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    open(image, button);
                });
                host.appendChild(button);
            }
        }

        image.addEventListener('click', () => open(image, image));
        image.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open(image, image);
            }
        });
    });

    closeButton.addEventListener('click', close);
    modal.addEventListener('click', (event) => {
        if (event.target === modal) close();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('open')) close();
    });
})();
