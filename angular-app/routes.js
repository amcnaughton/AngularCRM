(function() {
    'use strict';

    angular
        .module('app')
        .config(['$stateProvider', '$urlRouterProvider', function($stateProvider, $urlRouterProvider) {

            // For any unmatched url, send to /Dashboard
            $urlRouterProvider.otherwise("/Dashboard");

            $stateProvider
                .state('Dashboard', {
                    url: "/Dashboard",
                    templateUrl: componentURL + '/templates/dashboard.html',
                    controller: 'dashboardCtrl'
                });

        }]);

})();
