(function () {
    'use strict';

    angular
        .module('app')
        .controller('editProfileCtrl', editProfileCtrl);

    editProfileCtrl.$inject = ['$scope', '$modal', '$modalInstance', 'contactId', '$http'];

    /**
     * Edit profile support
     */
    function editProfileCtrl($scope, $modal, $modalInstance, contactId, $http) {

        $scope.contactId = contactId;

        // grab the requested contact and assign to scope
        $http.get(apiURL + "/contacts/" + $scope.contactId)
            .success(function (response) {
                $scope.contact = response[0];
            });

        // save the contact and close modal
        $scope.save = function() {

            // close the modal
            $modalInstance.close();

            // save the contact
            $http.post(apiURL + "/contacts/" + $scope.contactId, $scope.contact)
                .success(function (response) {
                });
        };

        // user wants outta here
        $scope.cancel = function() {
            $modalInstance.close();
        };

    }

})();
