# EasyAdmin Filters Bundle
EasyAdmin Filters Bundle adds the filters in the view list.

#### Requirements
- Symfony ~3.4
- [EasyAdmin](https://github.com/EasyCorp/EasyAdminBundle) ^1.17

#### How to use
1) Use or extends "Coolshop\EasyAdminFilters\Controller\AdminFilterableController"
2) Define under "easy_admin.EntityName.list" the "filters" attribute like "fields"
``` yaml
easy_admin:
 User:
  list:
   filters:
    - username
    - lastLogin
   fields:
    - username
    - firstName
    - lastName
```

#### Features
- Use class controller or trait "Coolshop\EasyAdminFilters\Controller\Traits\Filterable"

## License
This software is published under the [MIT License](https://github.com/fabio-coolshop/easyadmin-filters-bundle/blob/master/LICENSE)