/**
 * @file
 * A tab (field group) with #states: its tab button shows and hides with it.
 */
((Drupal, once, $) => {
  const toggle = (pane, visible) => {
    const tab = $(pane).data('horizontalTab');
    if (!tab) {
      return;
    }
    if (visible) {
      tab.item.removeClass('horizontal-tab-hidden').show();
    }
    else {
      // Hides the button; shows the first other tab when this one was open.
      tab.tabHide();
    }
  };

  Drupal.behaviors.redresshubTabStates = {
    attach(context) {
      once('redresshub-tab-states', '.horizontal-tabs-pane[data-drupal-states]', context).forEach((pane) => {
        $(pane).on('state:visible', (event) => {
          if (event.target === pane) {
            toggle(pane, event.value);
          }
        });
        // The states were set before this behavior ran.
        toggle(pane, pane.style.display !== 'none');
      });
    },
  };
})(Drupal, once, jQuery);
