const header = document.querySelector('[data-header]');
const menuButton = document.querySelector('[data-mobile-menu]');
const menu = document.querySelector('[data-menu]');

function closeMobileMenu() {
    menu?.classList.remove('visible');
    menuButton?.classList.remove('active');
    menuButton?.setAttribute('aria-expanded', 'false');
}

menuButton?.addEventListener('click', () => {
    const open = !menu?.classList.contains('visible');
    menu?.classList.toggle('visible', open);
    menuButton.classList.toggle('active', open);
    menuButton.setAttribute('aria-expanded', String(open));
});
menu?.querySelectorAll('a').forEach(link => link.addEventListener('click', closeMobileMenu));

window.addEventListener('scroll', () => {
    header?.classList.toggle('scrolled', window.scrollY > 20);
}, { passive: true });

const revealObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('revealed');
            revealObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.12 });
document.querySelectorAll('.reveal').forEach(element => revealObserver.observe(element));

document.querySelectorAll('.faq-list details').forEach(details => {
    details.addEventListener('toggle', () => {
        if (!details.open) return;
        document.querySelectorAll('.faq-list details').forEach(other => {
            if (other !== details) other.open = false;
        });
    });
});
