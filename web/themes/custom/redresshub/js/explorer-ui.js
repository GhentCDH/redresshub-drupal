/**
 * @file
 * The toolbar and side panel of the explorer on the front page.
 *
 * The map, list and network are Views (explorer_* views); this only adds:
 * - opening and closing the filters and the view menus of the toolbar;
 * - the side panel: a marker popup on the map (Leaflet Views) or a tile in
 *   the list opens its content in the panel next to the result.
 */

(($, Drupal, once) => {
  /**
   * Shows content in the side panel of an explorer view.
   */
  const openPanel = (container, html) => {
    const panel = container.querySelector('.explorer__panel');
    if (!panel) {
      return;
    }
    panel.querySelector('.explorer__panel-body').innerHTML = html;
    panel.hidden = false;
    panel.scrollTop = 0;
    // Next to a list or the network, a short result grows with the panel
    // (no scrollbar in the panel); the map keeps its height.
    const stage = panel.parentElement;
    stage.style.minHeight = '';
    if (stage.querySelector('.explorer__result--list')) {
      // Plus the border of the stage.
      stage.style.minHeight = `${panel.scrollHeight + stage.offsetHeight - stage.clientHeight}px`;
    }
    panel.querySelector('.explorer__panel-close').focus({ preventScroll: true });
  };

  /**
   * Closes the side panel, and gives the stage its own height again.
   */
  const closePanel = (panel) => {
    panel.hidden = true;
    panel.parentElement.style.minHeight = '';
  };

  // A popup of a map marker opens in the side panel instead.
  $(document).on('leafletMapInit', (event, settings, map, mapId) => {
    const container = document.getElementById(mapId)?.closest('.explorer__view');
    if (!container) {
      return;
    }
    map.on('popupopen', (e) => {
      const content = e.popup.getContent();
      map.closePopup(e.popup);
      openPanel(container, typeof content === 'string' ? content : content.outerHTML);
    });
  });

  Drupal.behaviors.redresshubExplorer = {
    attach(context) {
      once('redresshub-explorer', '[data-explorer]', context).forEach((explorer) => {
        const toggle = explorer.querySelector('.explorer__filter-toggle');
        const filters = explorer.querySelector('#explorer-filters');
        // Open or closed stays so while switching views and applying
        // filters (each loads a new page), for this browser tab.
        const remember = (open) => {
          try {
            sessionStorage.setItem('redresshub.explorer.filters', open ? 'open' : 'closed');
          }
          catch (e) {
            // No storage (private mode): the panel starts closed.
          }
        };
        const remembered = () => {
          try {
            return sessionStorage.getItem('redresshub.explorer.filters') === 'open';
          }
          catch (e) {
            return false;
          }
        };
        const setFilters = (open) => {
          if (filters) {
            filters.hidden = !open;
            toggle.setAttribute('aria-expanded', String(open));
          }
        };
        if (filters) {
          setFilters(remembered());
          toggle.addEventListener('click', () => {
            setFilters(filters.hidden);
            remember(!filters.hidden);
          });
        }
        else {
          toggle.hidden = true;
        }

        // View menus.
        const closeMenus = () => {
          explorer.querySelectorAll('.explorer__menu-list').forEach((list) => { list.hidden = true; });
          explorer.querySelectorAll('.explorer__menu > button').forEach((button) => button.setAttribute('aria-expanded', 'false'));
        };
        explorer.querySelectorAll('.explorer__menu').forEach((menu) => {
          const button = menu.querySelector('button');
          const list = menu.querySelector('.explorer__menu-list');
          button.addEventListener('click', () => {
            const open = list.hidden;
            closeMenus();
            list.hidden = !open;
            button.setAttribute('aria-expanded', String(open));
          });
        });
        document.addEventListener('click', (event) => {
          if (!event.target.closest('.explorer__menu')) {
            closeMenus();
          }
        });

        // Tiles open their content in the panel.
        explorer.addEventListener('click', (event) => {
          const tile = event.target.closest('[data-explorer-tile]');
          if (tile) {
            const template = tile.parentElement.querySelector('.explorer__tile-panel');
            openPanel(tile.closest('.explorer__view'), template ? template.innerHTML : '');
          }
          if (event.target.closest('.explorer__panel-close')) {
            closePanel(event.target.closest('.explorer__panel'));
          }
        });
        // A node of the network (network_views) opens its content in the
        // panel too; if that fails, its page.
        explorer.addEventListener('networkviews:nodeclick', (event) => {
          const nid = event.detail.id.replace(/^node:/, '');
          const container = event.target.closest('.explorer__view');
          if (!container || !/^\d+$/.test(nid)) {
            return;
          }
          event.preventDefault();
          fetch(Drupal.url(`explorer/panel/${nid}`))
            .then((response) => (response.ok ? response.text() : Promise.reject(response.status)))
            .then((html) => openPanel(container, html))
            .catch(() => {
              if (event.detail.url) {
                window.location.href = event.detail.url;
              }
            });
        });
        explorer.addEventListener('keydown', (event) => {
          if (event.key === 'Escape') {
            closeMenus();
            explorer.querySelectorAll('.explorer__panel').forEach(closePanel);
          }
        });
      });
    },
  };
})(jQuery, Drupal, once);

/**
 * Keeps the URL of a filtered explorer short: empty filters are not sent.
 */
((Drupal, once) => {
  Drupal.behaviors.redresshubExplorerForm = {
    attach(context) {
      once('redresshub-explorer-form', '.explorer__form', context).forEach((form) => {
        form.addEventListener('submit', (event) => {
          const reset = event.submitter && event.submitter.getAttribute('data-drupal-selector') === 'edit-reset';
          form.querySelectorAll('input[name], select[name]').forEach((field) => {
            const empty = field.value === '' || field.value === 'All';
            // Reset: drop all filters, keep only the chosen view.
            if (field.type === 'submit' || empty || (reset && field.name !== 'view')) {
              field.disabled = true;
            }
          });
        });
      });
    },
  };
})(Drupal, once);
