## Merge Reihenfolge 
### Remote -> Develop -> Staging(*) -> Production(*)
### * Remoteactions auf diesen branches feuern das deployment
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
