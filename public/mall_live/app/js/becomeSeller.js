/*成为卖家*/
app.controller('becomeSeller', ['$scope', '$rootScope', '$location', '$timeout', '$http', '$cookies', '$cookieStore', '$sce','myFactory', function($scope, $rootScope, $location, $timeout, $http, $cookies, $cookieStore, $sce,myFactory) {
  console.log('成为卖家');
  /*
  * 获取入驻协议
  */
  $scope.getHtml(3);

}])
/*填写资料*/
.controller('fillUserInfo', ['$scope', '$rootScope', '$location', '$timeout', '$http', '$cookies', '$cookieStore', '$sce','myFactory', function($scope, $rootScope, $location, $timeout, $http, $cookies, $cookieStore, $sce,myFactory) {
  console.log('填写资料');
  
}])