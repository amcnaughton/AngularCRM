(function () {
    'use strict';

    angular
        .module('app')
        .controller('dashboardCtrl', dashboardCtrl);

    dashboardCtrl.$inject = ['$scope', '$http', '$modal', 'ModalDialog'];

    /**
     * Display search panel
     */
    function dashboardCtrl($scope, $http, $modal, ModalDialog) {

        // set default search filter
        if($scope.form === undefined) {
            $scope.form = {};
            $scope.form.type = 'All';
        }

        // grab the organizations list
        if($scope.org_list === undefined) {
            $http.get(apiURL + "/organizations")
                .success(function (response) {
                    $scope.org_list = response[0];
                });
        }

        // grab the list of entity tags
        if($scope.tags === undefined) {
            $http.get(apiURL + "/tags")
                .success(function (response) {
                    $scope.tags = response[0];
                });
        }

        // grab the list of relationship types
        if($scope.relationship_types === undefined) {
            $http.get(apiURL + "/relationship_types")
                .success(function (response) {
                    $scope.relationship_types = response[0];
                });
        }

        // grab the list of sme tags
        if($scope.smes === undefined) {
            $http.get(apiURL + "/smes")
                .success(function (response) {
                    $scope.smes = response[0];
                });
        }

        // user clicked the search button
        $scope.search = function () {
            var query;

            query = "/contacts?" + jQuery.param($scope.form);

            $http.get(apiURL + query)
                .success(function (response) {
                    $scope.results = response[0];
                });
        }

        // clear search params
        $scope.clear = function () {

            $scope.results = undefined;
            $scope.form = {};
            $scope.form.type = 'All';
        };

        // open the selected profile in a modal
        $scope.showProfile = function(template, contactId) {

            ModalDialog.showProfile($modal, template, contactId);
        };

    }

})();
