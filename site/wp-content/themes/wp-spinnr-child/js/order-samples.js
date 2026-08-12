(function ($) {
    $(document).ready(function () {

        var waitForEl = function (selector, callback, count) {
            if ($(selector).length) {
                callback();
            } else {
                setTimeout(function () {
                    if (!count) count = 0;
                    count++;
                    if (count < 5) {
                        waitForEl(selector, callback, count);
                    }
                }, 500);
            }
        };

        function appendOR(selector) {
            $(selector).each(function () {
                $(this).before('<p class="my-auto mx-sm">OR</p>');
            });
        }

        waitForEl('.frm_radio:not(:first)', function () {
            appendOR('.frm_radio:not(:first)');
        });

        $(document).on('frmPageChanged', function () {
            waitForEl('.frm_radio:not(:first)', function () {
                appendOR('.frm_radio:not(:first)');
            });
        });

        // Address autocomplete, probe-first: the street field is only enhanced
        // after the key has answered one throwaway Places request successfully.
        // A rejected or broken key means the probe fails and the field is never
        // bound, so it stays a plain text input with no error state to clean up.
        var FIELDS = {
            street: '#field_4dysb2',
            suburb: '#field_oieam2',
            state: '#field_xumj02',
            postcode: '#field_nlwh12',
            country: '#field_sdzsl2'
        };
        var placesProven = false;

        function pick(place, type, form) {
            for (var i = 0; i < place.address_components.length; i++) {
                if ($.inArray(type, place.address_components[i].types) !== -1) {
                    return place.address_components[i][form];
                }
            }
            return '';
        }

        function setField(selector, value) {
            if (value) {
                $(selector).filter(':visible').first().val(value).trigger('change');
            }
        }

        function bindStreet() {
            if (!placesProven) {
                return;
            }

            var street = $(FIELDS.street).filter(':visible').first();
            if (!street.length || street.data('hcpAcBound')) {
                return;
            }
            street.data('hcpAcBound', true);

            // Picking a suggestion with Enter must not advance the form step.
            street.on('keydown', function (e) {
                if (e.key === 'Enter' && $('.pac-container:visible').length) {
                    e.preventDefault();
                }
            });

            var autocomplete = new google.maps.places.Autocomplete(street.get(0), {
                types: ['address'],
                fields: ['address_components'],
                componentRestrictions: { country: ['au', 'nz'] }
            });

            autocomplete.addListener('place_changed', function () {
                var place = autocomplete.getPlace();
                if (!place || !place.address_components) {
                    return;
                }
                setField(FIELDS.street, $.trim(
                    pick(place, 'street_number', 'long_name') + ' ' + pick(place, 'route', 'long_name')
                ));
                setField(FIELDS.suburb,
                    pick(place, 'locality', 'long_name') ||
                    pick(place, 'postal_town', 'long_name') ||
                    pick(place, 'sublocality', 'long_name'));
                setField(FIELDS.state, pick(place, 'administrative_area_level_1', 'short_name'));
                setField(FIELDS.postcode, pick(place, 'postal_code', 'long_name'));
                setField(FIELDS.country, pick(place, 'country', 'long_name'));
            });
        }

        function probePlaces(tries) {
            if (window.google && google.maps && google.maps.places) {
                new google.maps.places.AutocompleteService().getPlacePredictions(
                    { input: '1 George Street Sydney', componentRestrictions: { country: 'au' } },
                    function (predictions, status) {
                        if (status === 'OK') {
                            placesProven = true;
                            bindStreet();
                        }
                    }
                );
            } else if (tries > 0) {
                setTimeout(function () {
                    probePlaces(tries - 1);
                }, 500);
            }
        }

        probePlaces(20);
        $(document).on('frmPageChanged', bindStreet);
    });
})(jQuery);
