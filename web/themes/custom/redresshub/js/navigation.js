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

  /**
   * The language switcher: opens its menu, closes it on a click elsewhere or
   * Escape.
   */
  Drupal.behaviors.redresshubLanguageSwitcher = {
    attach(context) {
      once('redresshub-language-switcher', '.language-switcher__toggle', context).forEach((toggle) => {
        const menu = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!menu) {
          return;
        }
        const setOpen = (open) => {
          toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
          menu.hidden = !open;
        };
        toggle.addEventListener('click', () => setOpen(menu.hidden));
        document.addEventListener('click', (event) => {
          if (!toggle.parentElement.contains(event.target)) {
            setOpen(false);
          }
        });
        toggle.parentElement.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && !menu.hidden) {
            setOpen(false);
            toggle.focus();
          }
        });
      });
    },
  };
})(Drupal, once);
