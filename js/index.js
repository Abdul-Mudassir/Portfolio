(function() {
    'use strict';

    const sectionIds = ['header', 'about', 'portfolio', 'blog', 'contact'];
    const navigation = document.getElementById('navigation-content');
    const cursorEl = document.querySelector('.cursor');
    const themeLinkId = 'theme-style';

    const hideAllSections = () => {
        sectionIds.forEach((id) => {
            const section = document.getElementById(id);
            if (section) section.style.display = 'none';
        });
    };

    const navigateTo = (sectionId) => {
        if (!navigation) return;
        gsap.set(navigation, { display: 'none', y: '-100%' });
        hideAllSections();
        gsap.set('#breaker, #breaker-two', { display: 'block' });
        const target = document.getElementById(sectionId);
        if (target) gsap.set(target, { display: 'block', delay: 0.7 });
        gsap.set('#breaker, #breaker-two', { display: 'none', delay: 2 });
        gsap.set(navigation, { display: 'flex', delay: 2 });
    };

    const initLoader = () => {
        window.addEventListener('load', () => {
            gsap.to('#loader', {
                duration: 1,
                y: '-100%',
                opacity: 0,
                onComplete: () => {
                    gsap.set('#loader', { display: 'none' });
                    gsap.set('#header', { display: 'block' });
                    if (navigation) gsap.set(navigation, { display: 'flex' });
                }
            });
        });
    };

    const initNavigation = () => {
        const menubar = document.querySelector('.menubar');
        const closeButton = document.querySelector('.navigation-close');

        if (menubar && navigation) {
            menubar.addEventListener('click', () => gsap.to(navigation, { duration: 0.6, y: 0, display: 'flex' }));
        }

        if (closeButton && navigation) {
            closeButton.addEventListener('click', () => gsap.to(navigation, { duration: 0.6, y: '-100%' }));
        }

        const navMap = {
            'home-link': 'header',
            'about-link': 'about',
            'portfolio-link': 'portfolio',
            'blog-link': 'blog',
            'contact-link': 'contact'
        };

        Object.entries(navMap).forEach(([linkId, sectionId]) => {
            const link = document.getElementById(linkId);
            if (!link) return;
            link.addEventListener('click', (event) => {
                event.preventDefault();
                navigateTo(sectionId);
            });
        });
    };

    const initThemeSwitcher = () => {
        const panel = document.querySelector('.color-panel');
        if (panel) {
            panel.addEventListener('click', (event) => {
                event.preventDefault();
                document.querySelector('.color-changer')?.classList.toggle('color-changer-active');
            });
        }

        document.querySelectorAll('.colors a').forEach((link) => {
            link.addEventListener('click', (event) => {
                event.preventDefault();
                const theme = link.getAttribute('title');
                if (!theme) return;

                let themeLink = document.getElementById(themeLinkId);
                if (!themeLink) {
                    themeLink = document.createElement('link');
                    themeLink.id = themeLinkId;
                    themeLink.rel = 'stylesheet';
                    document.head.appendChild(themeLink);
                }
                themeLink.href = `css/${theme}.css`;
            });
        });
    };

    const initTypewriter = () => {
        function TxtRotate(el, toRotate, period) {
            this.toRotate = toRotate;
            this.el = el;
            this.loopNum = 0;
            this.period = parseInt(period, 10) || 2000;
            this.txt = '';
            this.isDeleting = false;
            this.tick();
        }

        TxtRotate.prototype.tick = function() {
            const i = this.loopNum % this.toRotate.length;
            const fullTxt = this.toRotate[i];

            this.txt = this.isDeleting ? fullTxt.substring(0, this.txt.length - 1) : fullTxt.substring(0, this.txt.length + 1);
            this.el.innerHTML = `<span class="wrap">${this.txt}</span>`;

            let delta = 200 - Math.random() * 100;
            if (this.isDeleting) delta /= 2;

            if (!this.isDeleting && this.txt === fullTxt) {
                delta = this.period;
                this.isDeleting = true;
            } else if (this.isDeleting && this.txt === '') {
                this.isDeleting = false;
                this.loopNum += 1;
                delta = 100;
            }

            setTimeout(() => this.tick(), delta);
        };

        const elements = document.getElementsByClassName('txt-rotate');
        Array.from(elements).forEach((element) => {
            const toRotate = element.getAttribute('data-rotate');
            const period = element.getAttribute('data-period');
            if (toRotate) new TxtRotate(element, JSON.parse(toRotate), period);
        });

        const style = document.createElement('style');
        style.textContent = '.txt-rotate > .wrap { border-right: 0em solid #666; }';
        document.body.appendChild(style);
    };

    const initParticles = () => {
        if (typeof particlesJS !== 'function' || !document.getElementById('particles')) return;

        particlesJS('particles', {
            particles: {
                number: { value: 40, density: { enable: true, value_area: 800 } },
                color: { value: '#ffffff' },
                shape: { type: 'circle', stroke: { width: 0, color: '#000000' } },
                opacity: { value: 0.5, random: false },
                size: { value: 3, random: true },
                line_linked: { enable: true, distance: 150, color: '#ffffff', opacity: 0.4, width: 1 },
                move: { enable: true, speed: 6, direction: 'none', random: false, straight: false, out_mode: 'out', bounce: false }
            },
            interactivity: {
                detect_on: 'canvas',
                events: {
                    onhover: { enable: true, mode: 'repulse' },
                    onclick: { enable: true, mode: 'push' },
                    resize: true
                },
                modes: {
                    grab: { distance: 400, line_linked: { opacity: 1 } },
                    bubble: { distance: 400, size: 40, duration: 2, opacity: 8, speed: 3 },
                    repulse: { distance: 200, duration: 0.4 },
                    push: { particles_nb: 4 },
                    remove: { particles_nb: 2 }
                }
            },
            retina_detect: true
        });
    };

    const initCursor = () => {
        if (!cursorEl) return;
        window.addEventListener('mousemove', (event) => {
            gsap.to(cursorEl, { x: event.clientX, y: event.clientY, stagger: 0.002, overwrite: 'auto' });
        });

        const hoverTargets = document.querySelectorAll('.menubar, a, .navigation-close');
        hoverTargets.forEach((target) => {
            target.addEventListener('mouseenter', () => gsap.to(cursorEl, { scale: 1.4, opacity: 1 }));
            target.addEventListener('mouseleave', () => gsap.to(cursorEl, { scale: 1, opacity: 0.6 }));
        });
    };

    const initPortfolioShowcase = () => {
        const cards = document.querySelectorAll('[data-tilt-card]');
        if (!cards.length) return;

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        cards.forEach((card) => {
            const resetTilt = () => {
                card.classList.remove('is-active');
                card.style.setProperty('--portfolio-rotate-x', '0deg');
                card.style.setProperty('--portfolio-rotate-y', '0deg');
            };

            card.addEventListener('pointerenter', (event) => {
                if (event.pointerType === 'touch') return;
                card.classList.add('is-active');
            });

            card.addEventListener('pointerleave', resetTilt);

            if (reduceMotion) return;

            card.addEventListener('pointermove', (event) => {
                if (event.pointerType === 'touch') return;
                const rect = card.getBoundingClientRect();
                const x = event.clientX - rect.left;
                const y = event.clientY - rect.top;
                const rotateY = ((x / rect.width) - 0.5) * 10;
                const rotateX = (((y / rect.height) - 0.5) * -8);

                card.style.setProperty('--portfolio-rotate-x', `${rotateX.toFixed(2)}deg`);
                card.style.setProperty('--portfolio-rotate-y', `${rotateY.toFixed(2)}deg`);
            });
        });
    };

    const initContactForm = () => {
        const form = document.getElementById('myForm');
        if (!form) return;

        const confirmation = document.getElementById('confirmation');
        const error = document.getElementById('error');
        const toast = document.getElementById('contact-toast');
        const toastMessage = document.getElementById('contact-toast-message');
        const toastClose = document.getElementById('contact-toast-close');
        const submitButton = form.querySelector('button[type="submit"]');
        const defaultButtonText = submitButton ? submitButton.textContent : '';
        let toastTimer;

        const hideToast = () => {
            if (!toast) return;
            toast.classList.remove('show');
            toast.setAttribute('aria-hidden', 'true');
            window.clearTimeout(toastTimer);
        };

        const showToast = (success, message) => {
            if (!toast || !toastMessage) return;
            window.clearTimeout(toastTimer);
            toastMessage.textContent = message;
            toast.classList.toggle('success', success);
            toast.classList.toggle('error', !success);
            toast.setAttribute('role', success ? 'status' : 'alert');
            toast.setAttribute('aria-hidden', 'false');
            toast.classList.add('show');
            toastTimer = window.setTimeout(hideToast, 5000);
        };

        const setStatus = (success, message) => {
            if (confirmation) {
                confirmation.textContent = success ? message : '';
                confirmation.style.display = success ? 'block' : 'none';
            }

            if (error) {
                error.textContent = success ? '' : message;
                error.style.display = success ? 'none' : 'block';
            }

            showToast(success, message);
        };

        if (toastClose) {
            toastClose.addEventListener('click', hideToast);
        }

        const parseResponse = async (response) => {
            const raw = await response.text();
            try {
                return JSON.parse(raw);
            } catch (err) {
                return {
                    success: false,
                    message: raw.trim() || 'Message could not be sent. Please try again.'
                };
            }
        };

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(form);

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Sending...';
            }

            try {
                const response = await fetch('sendEmail.php', {
                    method: 'POST',
                    body: formData,
                    headers: { Accept: 'application/json' }
                });
                const payload = await parseResponse(response);
                const success = response.ok && payload.success === true;
                setStatus(success, payload.message || 'Message could not be sent. Please try again.');
                if (success) form.reset();
            } catch (err) {
                setStatus(false, 'Message could not be sent. Please check your connection and try again.');
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = defaultButtonText;
                }
            }
        });
    };

    const init = () => {
        initLoader();
        initNavigation();
        initThemeSwitcher();
        initTypewriter();
        initParticles();
        initCursor();
        initPortfolioShowcase();
        initContactForm();
    };

    document.addEventListener('DOMContentLoaded', init);
})();
