/**
 * @file
 * Fragment form: the media library shows only the media types that fit the
 * type of the fragment (images for an image, …). The form checks it again
 * when saving (AdminHooks::fragmentMediaValidate()).
 */

((Drupal, drupalSettings, once) => {
  const allowed = {
    image: ['image'],
    audio: ['audio'],
    video: ['video', 'remote_video'],
  };

  Drupal.behaviors.redresshubFragmentMedia = {
    attach(context) {
      const select = document.querySelector('select[name="field_fragment_type"]');
      if (!select) {
        return;
      }
      once('redresshub-fragment-media', '.js-media-library-menu', context).forEach((menu) => {
        const type = (drupalSettings.redresshubFragmentTypes || {})[select.value];
        const types = allowed[type];
        // Only for the media of the fragment (not the images of a slideshow,
        // which are images only).
        if (!types || !menu.querySelector('a[href*="field_name%5D=field_media&"]')) {
          return;
        }
        const items = Array.from(menu.querySelectorAll('.media-library-menu__item'));
        const fits = (item) => types.some((t) => item.classList.contains(`media-library-menu-${t}`));
        items.forEach((item) => {
          item.hidden = !fits(item);
        });
        const active = menu.querySelector('.media-library-menu__link.active');
        if (active && !fits(active.closest('.media-library-menu__item'))) {
          // After the media library has made its links load in the dialog.
          const link = items.find(fits)?.querySelector('a');
          setTimeout(() => link?.click(), 0);
        }
      });
    },
  };
})(Drupal, drupalSettings, once);
