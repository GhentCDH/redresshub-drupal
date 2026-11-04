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
  /**
   * Places an open side panel. The page stays where it is: with the top of
   * the stage out of sight, the panel starts below the header, where the
   * visitor is looking. In full screen the stage is all there is: the panel
   * starts at its top.
   */
  const placePanel = (panel) => {
    const header = document.querySelector('.site-header');
    const visibleTop = (header ? header.getBoundingClientRect().bottom : 0) + 16;
    const offset = document.fullscreenElement ? 0 : Math.max(0, visibleTop - panel.parentElement.getBoundingClientRect().top);
    panel.style.top = `${offset}px`;
    panel.style.minHeight = `calc(100% - ${offset}px)`;
    panel.redresshubFit?.();
  };

  // Into or out of full screen: an open panel at once in its place.
  document.addEventListener('fullscreenchange', () => {
    document.querySelectorAll('.explorer__panel:not([hidden])').forEach(placePanel);
  });

  const openPanel = (container, html) => {
    const panel = container.querySelector('.explorer__panel');
    if (!panel) {
      return;
    }
    panel.querySelector('.explorer__panel-body').innerHTML = html;
    // A tag is a filter where this view has that filter with that term.
    const form = container.querySelector('form.explorer__form');
    panel.querySelectorAll('[data-explorer-filter]').forEach((tag) => {
      const select = form?.querySelector(`select[name="${tag.dataset.explorerFilter}"]`);
      if (!select || ![...select.options].some((option) => option.value === tag.dataset.explorerValue)) {
        tag.replaceWith(document.createTextNode(tag.textContent));
      }
    });
    panel.hidden = false;
    placePanel(panel);
    panel.querySelector('.explorer__panel-close').focus({ preventScroll: true });
  };

  /**
   * Shows the content of a node (research data) in the side panel.
   */
  const openNode = (container, nid) => {
    fetch(Drupal.url(`explorer/panel/${nid}`))
      .then((response) => (response.ok ? response.text() : Promise.reject(response.status)))
      .then((html) => openPanel(container, html))
      .catch(() => {});
  };

  /**
   * Keeps what comes after the explorer (the footer; on small screens the
   * cards) below a side panel that is longer than the explorer: the explorer
   * gets the overflow as its bottom margin, whenever the panel changes size,
   * opens or closes. The note below the map stays where it is.
   */
  const fitPanel = (panel) => {
    const explorer = panel.closest('[data-explorer]');
    const fit = () => {
      explorer.style.marginBottom = '';
      if (!panel.hidden && !document.fullscreenElement) {
        const over = panel.getBoundingClientRect().bottom - explorer.getBoundingClientRect().bottom;
        if (over > 0) {
          explorer.style.marginBottom = `${Math.ceil(over) + 20}px`;
        }
      }
    };
    if (typeof ResizeObserver !== 'undefined') {
      new ResizeObserver(fit).observe(panel);
    }
    // Also when it moves without changing size (see openPanel()).
    panel.redresshubFit = fit;
  };

  /**
   * Closes the side panel.
   */
  const closePanel = (panel) => {
    panel.hidden = true;
  };

  /**
   * A full screen button for a stage (map or network, with the time bar and
   * the side panel): the whole stage fills the screen. Not made where the
   * browser cannot do it (e.g. on iPhones). onChange runs once the new size
   * is laid out.
   */
  const fullscreenButton = (stage, onChange) => {
    if (!document.fullscreenEnabled || !stage) {
      return null;
    }
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'explorer__fullscreen-button';
    const update = () => {
      const on = document.fullscreenElement === stage;
      button.innerHTML = `<span class="icon icon--${on ? 'minimize' : 'maximize'}" aria-hidden="true"></span>`;
      button.setAttribute('aria-label', on ? Drupal.t('Exit full screen') : Drupal.t('Full screen'));
      button.title = button.getAttribute('aria-label');
    };
    button.addEventListener('click', () => {
      if (document.fullscreenElement === stage) {
        document.exitFullscreen();
      }
      else {
        stage.requestFullscreen().catch(() => {});
      }
    });
    document.addEventListener('fullscreenchange', () => {
      if (!button.isConnected) {
        return;
      }
      update();
      window.requestAnimationFrame(() => window.requestAnimationFrame(() => onChange?.()));
    });
    update();
    return button;
  };

  /**
   * The full screen button on a map: a Leaflet control at the top left, below
   * the zoom and reset buttons (the side panel covers the right).
   */
  const addFullscreen = (map, stage) => {
    const button = fullscreenButton(stage, () => map.invalidateSize());
    if (!button) {
      return;
    }
    const Control = L.Control.extend({
      options: { position: 'topleft' },
      onAdd() {
        const bar = L.DomUtil.create('div', 'leaflet-bar explorer__fullscreen');
        bar.appendChild(button);
        L.DomEvent.disableClickPropagation(bar);
        return bar;
      },
    });
    map.addControl(new Control());
  };

  // The view of each map when the page was loaded: the reset button goes
  // back to it, also after filtering (Leaflet would use the view it started
  // from, and computes that without the padding of its fitBounds options).
  const initialViews = {};

  // A popup of a map marker opens in the side panel instead.
  $(document).on('leafletMapInit', (event, settings, map, mapId) => {
    const container = document.getElementById(mapId)?.closest('.explorer__view');
    if (!container) {
      return;
    }
    const reset = Drupal.Leaflet[mapId]?.reset_view_control;
    const applyReset = () => {
      if (reset) {
        reset.options.latlng = initialViews[mapId].latlng;
        reset.options.zoom = initialViews[mapId].zoom;
      }
    };
    if (!initialViews[mapId]) {
      // Leaflet settles on its first view just after this event: take the
      // view it moves to right after loading, not a later move of the visitor.
      const loaded = Date.now();
      initialViews[mapId] = { latlng: map.getCenter(), zoom: map.getZoom() };
      map.once('moveend', () => {
        if (Date.now() - loaded < 1000) {
          initialViews[mapId] = { latlng: map.getCenter(), zoom: map.getZoom() };
          applyReset();
        }
      });
    }
    applyReset();
    addFullscreen(map, container.querySelector('.explorer__stage'));
    // The attribution without the Leaflet prefix; the map data and tiles
    // themselves must stay credited.
    map.attributionControl?.setPrefix(false);
    map.on('popupopen', (e) => {
      const content = e.popup.getContent();
      map.closePopup(e.popup);
      openPanel(container, typeof content === 'string' ? content : content.outerHTML);
    });
  });

  /**
   * Aligns the cards next to the explorer with the top of the stage (the
   * map, list or network), also when the filter panel opens.
   */
  const alignSidebar = (explorer) => {
    const layout = explorer.closest('.map-layout');
    const stage = explorer.querySelector('.explorer__stage');
    if (!layout || !stage || typeof ResizeObserver === 'undefined') {
      return;
    }
    const align = () => {
      if (stage.isConnected) {
        const offset = stage.getBoundingClientRect().top - layout.getBoundingClientRect().top;
        layout.style.setProperty('--map-layout-stage-top', `${Math.max(0, Math.round(offset))}px`);
      }
    };
    new ResizeObserver(align).observe(explorer);
    align();
  };

  Drupal.behaviors.redresshubExplorer = {
    attach(context) {
      once('redresshub-explorer', '[data-explorer]', context).forEach((explorer) => {
        alignSidebar(explorer);
        // The network in full screen too: the button at the top left of the
        // graph, as on the map (the graph library follows the size of its
        // canvas on a resize).
        const network = explorer.querySelector('.explorer__stage .network-views');
        if (network) {
          const button = fullscreenButton(network.closest('.explorer__stage'), () => window.dispatchEvent(new Event('resize')));
          if (button) {
            button.classList.add('explorer__fullscreen-button--network');
            network.prepend(button);
          }
        }
        explorer.querySelectorAll('.explorer__panel').forEach(fitPanel);
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
          // A tag in the panel: that term as the filter of this view.
          const tag = event.target.closest('[data-explorer-filter]');
          if (tag) {
            const form = tag.closest('.explorer__view')?.querySelector('form.explorer__form');
            const select = form?.querySelector(`select[name="${tag.dataset.explorerFilter}"]`);
            if (select) {
              select.value = tag.dataset.explorerValue;
              form.requestSubmit();
            }
          }
          // Linked research data in the panel (it has no page of its own).
          const link = event.target.closest('[data-explorer-nid]');
          if (link) {
            openNode(link.closest('.explorer__view'), link.dataset.explorerNid);
          }
          if (event.target.closest('.explorer__panel-close')) {
            closePanel(event.target.closest('.explorer__panel'));
          }
        });
        // A node of the network (network_views) opens its content in the
        // panel too.
        explorer.addEventListener('networkviews:nodeclick', (event) => {
          const nid = event.detail.id.replace(/^node:/, '');
          const container = event.target.closest('.explorer__view');
          if (!container || !/^\d+$/.test(nid)) {
            return;
          }
          event.preventDefault();
          openNode(container, nid);
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
