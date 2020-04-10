<?php namespace RainLab\Docs\Controllers;

/**
 * Base controller.
 *
 * Extends the BackendController to route the top-level route (`backend/docs`) to the correct controller and action.
 *
 * @author Ben Thomson
 */
class Base extends \Backend\Classes\BackendController
{
    public function run($url = null)
    {
        return parent::run('rainlab/docs/index/index/' . $url);
    }
}
