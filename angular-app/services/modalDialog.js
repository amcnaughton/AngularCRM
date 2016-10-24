(function () {
    'use strict';

    angular
        .module('app')
        .service('ModalDialog', function() {

            /**
             * Modal dialog support function (view a profile)
             */
            this.showProfile = function(modal, template, contactId)
            {
                modal.open({
                    animation: true,
                    templateUrl: '/components/com_exchange/app/templates/' + template,
                    controller: 'showProfileCtrl',
                    size: 'lg',
                    resolve: {
                        contactId: function () {
                            return contactId;
                        }
                    }
                })
            };

            /**
             * Modal dialog support functions (edit a profile)
             */
            this.editProfile = function(modal, template, contactId)
            {
                modal.open({
                    animation: false,
                    templateUrl: '/components/com_exchange/app/templates/' + template,
                    controller: 'editProfileCtrl',
                    size: 'lg',
                    resolve: {
                        contactId: function() {
                            return contactId;
                        }
                    }
                });
            };

        })

})();
