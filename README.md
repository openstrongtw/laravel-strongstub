<h1 align="center">Larevel-StrongStub</h1>
<h3 align="center">在十秒內建立完成 CURD 增刪改查的邏輯。</h3>

安裝
-------

```
composer require --prefer-dist openstrong/laravel-strongstub
```
使用示例 
-------

建立一個包含驗證規則的 Eloquent Model 模型
```
php artisan strongstub:model Models/StrongadminUser -t strongadmin_user -c mysql

參數說明：
-t          表名稱
-c          數據庫連線名稱，預設為 mysql，（config/database.php）
--force     是否強制覆蓋
```

建立一個 CURD（增刪改查）controller class
```
php artisan strongstub:curd Strongadmin/TestAdminUserController -m App\\Models\\StrongadminUser -e App\\Http\\Controllers\\Controller

參數說明：
-m          Eloquent Model 模型
-e          要繼承的 controller 控制器，預設預設值："App\Http\Controllers\Controller"
--force     是否強制覆蓋
--view      是否建立 laravel-strongadmin 檢視檔案,如果是，則 -e 參數值被強制設定為 OpenStrong\StrongAdmin\Http\Controllers\BaseController
```

建立 api 介面 markdown 文件
```
php artisan strongstub:wiki Strongadmin/TestAdminUserController -m App\\Models\\StrongadminUser --force

參數說明：
-m          Eloquent Model 模型
--force     是否強制覆蓋
```

建立 laravel-strongadmin 檢視檔案，這裡推薦 使用 https://gitee.com/openstrong/laravel-strongadmin 擴充套件應用：在1分鐘內構建一個功能齊全的管理後臺。
```
php artisan strongstub:view strongadmin/testAdminUser -t strongadmin_user

參數說明：
-t          表名稱
--force     是否強制覆蓋
```

# 使用此擴充套件包的開源專案
StrongShop 開源跨境商城 https://gitee.com/openstrong/strongshop

QQ群：557655631