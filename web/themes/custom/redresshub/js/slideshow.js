/**
 * @file
 * Slideshow of a Text + media paragraph.
 *
 * The slides are a horizontal scroll-snap track, so swiping and the arrow
 * keys (when the track has focus) work without JavaScript; this adds the
 * previous/next buttons and the counter.
 */

((Drupal, once) => {
  Drupal.behaviors.redresshubSlideshow = {
    attach(context) {
      once('redresshub-slideshow', '.slideshow', context).forEach((slideshow) => {
        const track = slideshow.querySelector('.slideshow__track');
        const slides = Array.from(track.children);
        const controls = slideshow.querySelector('.slideshow__controls');
        const count = slideshow.querySelector('.slideshow__count');
        const prev = slideshow.querySelector('.slideshow__button--prev');
        const next = slideshow.querySelector('.slideshow__button--next');

        const current = () => Math.round(track.scrollLeft / track.clientWidth);
        const show = (index) => {
          const target = (index + slides.length) % slides.length;
          track.scrollTo({ left: target * track.clientWidth, behavior: 'smooth' });
        };
        const update = () => {
          count.textContent = `${current() + 1} / ${slides.length}`;
        };

        prev.addEventListener('click', () => show(current() - 1));
        next.addEventListener('click', () => show(current() + 1));
        track.addEventListener('scroll', () => window.requestAnimationFrame(update), { passive: true });
        controls.hidden = false;
      });
    },
  };
})(Drupal, once);
