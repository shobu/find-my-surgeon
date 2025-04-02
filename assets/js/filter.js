jQuery(document).ready(function($) {

    $('#fms_country').on('change', function() {
        var countryId = $(this).val();

        if (!countryId) {
            $('#fms_city').html('<option value="">Select City</option>').prop('disabled', true);
            return;
        }

        $.ajax({
            url: fms_ajax_obj.ajax_url,
            method: 'POST',
            data: {
                action: 'fms_get_cities',
                country_id: countryId
            },
            success: function(response) {
                var cities = JSON.parse(response);
            
                if (cities.length === 0) {
                    $('#fms_city').html('<option value="">No cities available</option>').prop('disabled', true);
                    return;
                }
            
                var options = '<option value="">Select City</option>';
            
                cities.forEach(function(city) {
                    options += '<option value="' + city.term_id + '">' + city.name + '</option>';
                });
            
                $('#fms_city').html(options).prop('disabled', false);
            },            
            error: function() {
                alert('Something went wrong loading cities.');
            }
        });
    });

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
