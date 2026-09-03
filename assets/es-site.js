(() => {
    const toggle = document.querySelector('[data-nav-toggle]');
    const nav = document.querySelector('[data-nav]');
    const navLinks = nav ? [...nav.querySelectorAll('[data-nav-link]')] : [];
    const indicator = nav ? nav.querySelector('[data-nav-indicator]') : null;

    if (toggle && nav) {
        const closeNavigation = () => {
            nav.classList.remove('open');
            toggle.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('nav-open');
        };

        toggle.addEventListener('click', () => {
            const open = !nav.classList.contains('open');
            nav.classList.toggle('open', open);
            toggle.setAttribute('aria-expanded', String(open));
            document.body.classList.toggle('nav-open', open);
        });

        navLinks.forEach((link) => {
            link.addEventListener('click', closeNavigation);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && nav.classList.contains('open')) {
                closeNavigation();
                toggle.focus();
            }
        });

        document.addEventListener('click', (event) => {
            if (!nav.classList.contains('open')) return;
            if (nav.contains(event.target) || toggle.contains(event.target)) return;
            closeNavigation();
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 980 && nav.classList.contains('open')) {
                closeNavigation();
            }
        }, { passive: true });
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
