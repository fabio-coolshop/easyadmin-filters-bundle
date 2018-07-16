<?php

namespace Coolshop\EasyAdminFilters\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AdminController;
use EasyCorp\Bundle\EasyAdminBundle\Event\EasyAdminEvents;

class AdminFilterableController extends AdminController {

	use Traits\Filterable;
	
}