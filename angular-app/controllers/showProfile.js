(function () {
    'use strict';

    angular
        .module('app')
        .controller('showProfileCtrl', showProfileCtrl);

    showProfileCtrl.$inject = ['$scope', '$modal', '$modalInstance', 'contactId', '$http', 'ModalDialog'];

    /**
     * Show Individual or Organization Profile
     */
    function showProfileCtrl($scope, $modal, $modalInstance, contactId, $http, ModalDialog) {

        $scope.contactId = contactId;

        $http.get(apiURL + "/contacts/" + $scope.contactId)
            .success(function (response) {
                $scope.contact = response[0];
            });

        $scope.ok = function() {
            $modalInstance.close();
        };

        // open the selected profile in a modal
        $scope.showProfile = function(template, contactId) {

            ModalDialog.showProfile($modal, template, contactId);
        };

        // edit the selected profile in a modal
        $scope.editProfile = function(template, contactId) {

            // close the existing show profile modal
            $modalInstance.close();

            // and open the edit profile modal
            ModalDialog.editProfile($modal, template, contactId);
        };

    }

})();
