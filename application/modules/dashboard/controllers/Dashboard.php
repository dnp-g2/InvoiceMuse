<?php

if ( ! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/*
 * InvoicePlane
 *
 * @author		InvoicePlane Developers & Contributors
 * @copyright	Copyright (c) 2012 - 2018 InvoicePlane.com
 * @license		https://invoiceplane.com/license.txt
 * @link		https://invoiceplane.com
 */

#[AllowDynamicProperties]
class Dashboard extends Admin_Controller
{
    public function index()
    {
        $this->load->model('dashboard/mdl_dashboard');
        $this->layout->set($this->mdl_dashboard->overview());
        $this->layout->buffer('content', 'dashboard/index');
        $this->layout->render();
    }
}
