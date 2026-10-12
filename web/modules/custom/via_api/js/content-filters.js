(function (Drupal, once, drupalSettings) {
  Drupal.behaviors.viaApiContentFilters = {
    attach(context) {
      once("via-api-content-filters", "form.views-exposed-form", context).forEach(form => {
        const page = form.querySelector('select[name="site_page"]');
        const section = form.querySelector('select[name="site_section"]');
        const sectionToPage = drupalSettings.viaApiContentFilters?.sectionToPage || {};
        if (!page || !section) return;

        const allOptions = Array.from(section.options, option => option.cloneNode(true));
        const refreshSections = () => {
          const pageId = page.value;
          const selectedSection = section.value;
          const showAll = !pageId || pageId === "All";
          const options = allOptions.filter(option =>
            !option.value || option.value === "All" || showAll || sectionToPage[option.value] === pageId
          );
          section.replaceChildren(...options);
          if (!options.some(option => option.value === selectedSection)) {
            section.value = "All";
          }
        };

        page.addEventListener("change", refreshSections);
        refreshSections();
      });
    },
  };
})(Drupal, once, drupalSettings);