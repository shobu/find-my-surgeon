console.log("filter.js loaded!");

jQuery(document).ready(function($) {
    $('#fms_search').on('click', function(e) {
        e.preventDefault();

        var cityId = $('#fms_city').val();

        if (!cityId) {
            alert('Please select a city.');
            return;
        }

        $('#doctors-results').html('<p>Loading...</p>');

        $.ajax({
            url: fms_ajax_obj.ajax_url,
            method: 'POST',
            data: {
                action: 'fms_get_doctors',
                city_id: cityId
            },
            success: function(response) {
                $('#doctors-results').html(response);

                const wrapper = document.querySelector('#doctors-results .fms-results-wrapper');
                if (wrapper && wrapper.classList.contains('has-results')) {
                    $('#doctors-results').addClass('has-results');
                } else {
                    $('#doctors-results').removeClass('has-results');
                }

                document.getElementById('doctors-results').scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            },
            error: function() {
                $('#doctors-results').html('<p>Error loading results.</p>');
            }
        });
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const countryDropdown = document.querySelector('#fms_country_dropdown');
    const countrySelected = countryDropdown.querySelector('.fms-dropdown-selected');
    const countryOptions = countryDropdown.querySelector('.fms-dropdown-options');
    const countryHiddenInput = document.querySelector('#fms_country');

    const cityDropdown = document.querySelector('#fms_city_dropdown');
    const citySelected = cityDropdown.querySelector('.fms-dropdown-selected');
    const cityOptions = cityDropdown.querySelector('.fms-dropdown-options');
    const cityHiddenInput = document.querySelector('#fms_city');

    function disableCityDropdown(message) {
        message = message || (typeof fms_strings !== 'undefined' ? fms_strings.select_city : 'Select City');
        cityDropdown.classList.add('disabled');
        citySelected.textContent = message;
        cityOptions.innerHTML = '';
        cityHiddenInput.value = '';
    }

    function populateCities(cities) {
        if (!cities.length) {
            disableCityDropdown(fms_strings.no_cities);
            return;
        }

        cityDropdown.classList.remove('disabled');
        cityOptions.innerHTML = '';
        cityHiddenInput.value = '';

        cities.forEach(city => {
            const li = document.createElement('li');
            li.textContent = city.name;
            li.setAttribute('data-value', city.term_id);
            cityOptions.appendChild(li);
        });

        citySelected.textContent = fms_strings.select_city;
    }

    countrySelected.addEventListener('click', function () {
        countryOptions.classList.toggle('show');
    });

    countryOptions.querySelectorAll('li').forEach(li => {
        li.addEventListener('click', function () {
            const selectedCountryName = this.textContent;
            const selectedCountryId = this.getAttribute('data-value');

            countrySelected.textContent = selectedCountryName;
            countryHiddenInput.value = selectedCountryId;

            disableCityDropdown();

            fetch(fms_ajax_obj.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=fms_get_cities&country_id=${selectedCountryId}`
            })
            .then(response => response.json())
            .then(data => {
                if (!Array.isArray(data)) {
                    console.warn('Expected array but got:', data);
                    disableCityDropdown(fms_strings.ajax_error);
                    return;
                }
                if (data.length === 0) {
                    disableCityDropdown(fms_strings.no_cities);
                return;
            }
                populateCities(data);
            })
            .catch(error => {
                console.error('Error loading cities:', error);
                disableCityDropdown(fms_strings.ajax_error);
            });

            countryOptions.classList.remove('show');
        });
    });

    citySelected.addEventListener('click', function () {
        if (cityDropdown.classList.contains('disabled')) return;
        cityOptions.classList.toggle('show');
    });

    cityOptions.addEventListener('click', function (e) {
        if (e.target.tagName === 'LI') {
            citySelected.textContent = e.target.textContent;
            cityHiddenInput.value = e.target.getAttribute('data-value');
            cityOptions.classList.remove('show');
        }
    });

    document.addEventListener('click', function (e) {
        if (!countryDropdown.contains(e.target)) {
            countryOptions.classList.remove('show');
        }
        if (!cityDropdown.contains(e.target)) {
            cityOptions.classList.remove('show');
        }
    });
});


function fmsTrackClick(doctorName, action) {
  if (typeof gtag === 'function') {
    gtag('event', 'click', {
      event_category: 'Doctor Interaction',
      event_label: doctorName,
      value: action
    });
  }
}
