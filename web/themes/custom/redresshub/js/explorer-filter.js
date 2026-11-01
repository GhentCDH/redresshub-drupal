/**
 * @file
 * Filtering the explorer without loading the page again, and the period
 * slider on the map and the network.
 *
 * - The period slider (search_api_range_filter) of the map and network
 *   displays moves from the filter panel onto the map or the network canvas
 *   (the time bar). Its
 *   fields stay in the exposed form through their form attribute, and moving
 *   a handle applies the filters.
 * - A chosen filter applies at once, without the Apply button; only the
 *   search field waits for its own search button (or Enter).
 * - Applying the filters fetches the front page with the new filters and
 *   swaps the explorer (toolbar included, so its links and filter count
 *   follow): the new map starts at the position of the old one, the filter
 *   panel stays open or closed. The URL follows, so the filtered view can be shared; back and
 *   forward load that page.
 *
 * Without JavaScript the slider is two number fields in the filter panel,
 * and the form loads the page as before.
 */

(($, Drupal, drupalSettings, once) => {
  // Whether the URL was changed here (back and forward then need a reload).
  let pushed = false;

  /**
   * Returns the URL of the exposed form with its values, without empty ones.
   */
  const formUrl = (form) => {
    const url = new URL(form.getAttribute('action') || window.location.pathname, window.location.href);
    new FormData(form).forEach((value, name) => {
      if (typeof value === 'string' && value !== '' && value !== 'All') {
        url.searchParams.append(name, value);
      }
    });
    return url.toString();
  };

  /**
   * Returns the URL of the current view without any filter.
   */
  const resetUrl = (form) => {
    const url = new URL(form.getAttribute('action') || window.location.pathname, window.location.href);
    const view = form.querySelector('input[name="view"]');
    if (view) {
      url.searchParams.set('view', view.value);
    }
    return url.toString();
  };

  /**
   * Returns a selector for the focused filter or slider handle, if any.
   */
  const focusSelector = (explorer) => {
    const active = document.activeElement;
    if (!active || !explorer.contains(active)) {
      return null;
    }
    if (active.matches('.noUi-handle')) {
      return `.noUi-handle[data-handle="${active.dataset.handle}"]`;
    }
    return active.name ? `[name="${active.name}"]` : null;
  };

  // The request in progress: a newer one replaces it.
  let request = null;

  /**
   * Loads the explorer of a URL in place of the current one.
   */
  const load = (explorer, url) => {
    request?.abort();
    request = new AbortController();
    const { signal } = request;
    explorer.classList.add('is-loading');
    explorer.setAttribute('aria-busy', 'true');
    // The position of the map, for the new map.
    const container = explorer.querySelector('.leaflet-container');
    const map = container ? $(container).data('leaflet')?.lMap : null;
    const mapView = map ? { id: container.id, center: map.getCenter(), zoom: map.getZoom() } : null;

    fetch(url, { credentials: 'same-origin', signal })
      .then((response) => (response.ok ? response.text().then((html) => [html, response.url]) : Promise.reject(response.status)))
      .then(([html, finalUrl]) => {
        const page = new DOMParser().parseFromString(html, 'text/html');
        const fresh = page.querySelector('[data-explorer]');
        if (!fresh) {
          throw new Error('No explorer');
        }
        // The map settings (markers) of the new page.
        const settings = page.querySelector('script[data-drupal-selector="drupal-settings-json"]');
        if (settings) {
          const parsed = JSON.parse(settings.textContent);
          drupalSettings.leaflet = parsed.leaflet || {};
        }
        // The new map starts where the old one was, instead of fitting its
        // markers.
        const mapSettings = mapView ? drupalSettings.leaflet[mapView.id]?.map?.settings : null;
        if (mapSettings) {
          mapSettings.map_position_force = true;
          mapSettings.center = { lat: mapView.center.lat, lon: mapView.center.lng };
          mapSettings.zoom = mapView.zoom;
        }
        // once() looks inside the context: attach to the parent, so the
        // explorer itself is found too.
        const parent = explorer.parentElement;
        const focus = focusSelector(explorer);
        Drupal.detachBehaviors(explorer, drupalSettings);
        explorer.replaceWith(fresh);
        Drupal.attachBehaviors(parent, drupalSettings);
        if (focus) {
          fresh.querySelector(focus)?.focus({ preventScroll: true });
        }
        window.history.pushState({ explorer: true }, '', finalUrl);
        pushed = true;
      })
      .catch(() => {
        if (!signal.aborted) {
          window.location.href = url;
        }
      });
  };

  // Back and forward: the page of that URL.
  window.addEventListener('popstate', (event) => {
    if (pushed || event.state?.explorer) {
      window.location.reload();
    }
  });

  Drupal.behaviors.redresshubExplorerFilter = {
    attach(context) {
      once('redresshub-explorer-filter', '[data-explorer]', context).forEach((explorer) => {
        const form = explorer.querySelector('form.explorer__form');
        if (!form) {
          return;
        }

        form.addEventListener('submit', (event) => {
          event.preventDefault();
          load(explorer, event.submitter?.name === 'op' ? resetUrl(form) : formUrl(form));
        });

        // Every filter but the search field applies at once.
        form.addEventListener('change', (event) => {
          if (event.target.matches('select, input:not([type="text"])')) {
            form.requestSubmit();
          }
        });

        // The search field gets its own button, in place of Apply.
        const search = form.querySelector('input[name="search"]');
        const apply = form.querySelector('.form-actions input[type="submit"]:not([name="op"])');
        if (search && apply) {
          const button = document.createElement('button');
          button.type = 'submit';
          button.className = 'explorer__search-button';
          button.setAttribute('aria-label', Drupal.t('Search'));
          button.innerHTML = '<span class="icon icon--search" aria-hidden="true"></span>';
          const wrapper = document.createElement('div');
          wrapper.className = 'explorer__search';
          search.before(wrapper);
          wrapper.append(search, button);
          apply.hidden = true;
        }

        // The period slider onto the stage.
        const slider = form.querySelector('[data-search-api-range-slider]');
        const stage = explorer.querySelector('.explorer__stage');
        if (!slider || !stage || !form.id) {
          return;
        }
        const item = slider.closest('fieldset') || slider;
        const bar = document.createElement('div');
        bar.className = 'explorer__timebar';
        // On the network: over the bottom of the canvas, above its legend
        // (the canvas itself stays where it is, for the graph library).
        const canvas = stage.querySelector('.network-views__canvas');
        if (canvas) {
          bar.classList.add('explorer__timebar--network');
          canvas.after(bar);
        }
        else {
          stage.appendChild(bar);
        }
        bar.appendChild(item);
        item.querySelectorAll('input, select').forEach((input) => input.setAttribute('form', form.id));

        // A handle moved (both fields change at once, the arrow keys move it
        // step by step): apply the filters once it rests.
        let timer;
        item.addEventListener('change', () => {
          clearTimeout(timer);
          timer = setTimeout(() => form.requestSubmit(), 400);
        });
      });
    },
  };
})(jQuery, Drupal, drupalSettings, once);
