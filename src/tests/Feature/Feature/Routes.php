<?php

namespace Tests\Feature;

trait Routes
{
    protected string $ROUTE_REGISTER           = '/register';
    protected string $ROUTE_LOGIN              = '/login';
    protected string $ROUTE_HOME               = '/';
    protected string $ROUTE_STAMP              = '/attendance/stamp';
    protected string $ROUTE_CLOCK_IN           = '/attendance/clock-in';
    protected string $ROUTE_BREAK_IN           = '/attendance/break-in';
    protected string $ROUTE_BREAK_OUT          = '/attendance/break-out';
    protected string $ROUTE_CLOCK_OUT          = '/attendance/clock-out';
    protected string $ROUTE_USER_ATT_LIST      = '/attendance/list';
    protected string $ROUTE_USER_ATT_DETAIL    = '/attendance/detail'; // ?date=YYYY-MM-DD

    // 管理者
    protected string $ROUTE_ADMIN_LOGIN        = '/admin/login';
    protected string $ROUTE_ADMIN_ATT_LIST     = '/admin/attendance/list';   // ?date=YYYY-MM-DD
    protected string $ROUTE_ADMIN_ATT_DETAIL   = '/admin/attendance/detail'; // ?id={userId}&date=YYYY-MM-DD
    protected string $ROUTE_ADMIN_STAFF_LIST   = '/admin/staff/list';
    protected string $ROUTE_ADMIN_REQ_LIST     = '/admin/stamp_correction_request/list'; // ?status=pending|approved
    protected string $ROUTE_ADMIN_REQ_DETAIL   = '/admin/stamp_correction_request/detail'; // ?id={requestId}
    protected string $ROUTE_ADMIN_REQ_APPROVE  = '/admin/stamp_correction_request/approve'; // POST
}
