(function() {
    'use strict';

    /**
     * Decode the URL
     */
    angular
        .module('app')
        .filter('decodeUrl', function() {

            return function(url) {
                return url.replace(/&amp;/g, '&');
            };

        });

    /**
     * Place objects in the desired order
     */
    angular
        .module('app')
        .filter('orderObjectBy', function() {

        return function(items, field, reverse) {

            var filtered = [];

            angular.forEach(items, function(item) {
                filtered.push(item);
            });

            filtered.sort(function (a, b) {
                return (a[field] > b[field] ? 1 : -1);
            });

            if(reverse)
                filtered.reverse();

            return filtered;
        };

    })

    /**
     * Format fullname from a contact record
     */
    angular
        .module('app')
        .filter('fullName', function() {

        return function(contact) {
            if(contact !== undefined)
                return contact.first_name + " " + contact.last_name;
        };

    })

})();