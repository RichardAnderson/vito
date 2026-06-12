<?php

namespace App\Pages;

use RuntimeException;

/**
 * Thrown when a page's own actions/data collide by id with a component's — caught
 * early (route registration / boot) so a duplicate address is a loud error rather
 * than a silent last-wins/first-wins bug.
 */
class PageComponentException extends RuntimeException {}
