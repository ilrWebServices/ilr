(function (Drupal) {
  'use strict';

  Drupal.behaviors.jumpLinksMobile = {
    attach: function (context, settings) {
      // Find the jump links navigation block
      const navBlock = context.querySelector('.block-extra-field-block--node--report-summary--extra-field-ilr-section-navigation');
      
      if (!navBlock || navBlock.querySelector('.jump-links-mobile')) {
        return;
      }
      
      // Get all navigation links
      const navLinks = navBlock.querySelectorAll('.cu-page-nav__link');
      
      if (navLinks.length === 0) {
        return;
      }
      
      // Create mobile dropdown container
      const mobileContainer = document.createElement('div');
      mobileContainer.className = 'jump-links-mobile';
      
      // Create label
      const label = document.createElement('div');
      label.className = 'jump-links-label';
      label.textContent = 'JUMP TO:';
      mobileContainer.appendChild(label);
      
      // Create select element
      const select = document.createElement('select');
      select.className = 'jump-links-select';
      
      // Add default option
      const defaultOption = document.createElement('option');
      defaultOption.value = '';
      defaultOption.textContent = 'Select a section';
      select.appendChild(defaultOption);
      
      // Add options from navigation links
      navLinks.forEach(function (link) {
        const option = document.createElement('option');
        option.value = link.getAttribute('href');
        const heading = link.querySelector('.cu-heading');
        option.textContent = heading ? heading.textContent.trim() : link.textContent.trim();
        select.appendChild(option);
      });
      
      // Handle select change
      select.addEventListener('change', function () {
        if (this.value) {
          window.location.href = this.value;
        }
      });
      
      mobileContainer.appendChild(select);
      
      // Insert mobile dropdown at the beginning of the nav block
      const cuPageNav = navBlock.querySelector('.cu-page-nav');
      if (cuPageNav) {
        navBlock.insertBefore(mobileContainer, cuPageNav);
      } else {
        navBlock.insertBefore(mobileContainer, navBlock.firstChild);
      }
    }
  };

})(Drupal);
