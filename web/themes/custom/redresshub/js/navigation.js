/**
 * @file
 * Opens and closes the main navigation on small screens.
 */

((Drupal, once) => {
  Drupal.behaviors.redresshubNavigation = {
    attach(context) {
      once('redresshub-navigation', '.site-header__toggle', context).forEach(
        (toggle) => {
          const nav = document.getElementById(
            toggle.getAttribute('aria-controls'),
          );
          if (!nav) {
            return;
          }

          const setOpen = (open) => {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            nav.classList.toggle('is-open', open);
          };

          toggle.addEventListener('click', () => {
            setOpen(toggle.getAttribute('aria-expanded') !== 'true');
          });

          nav.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
              setOpen(false);
              toggle.focus();
            }
          });
        },
      );
    },
  };
})(Drupal, once);
