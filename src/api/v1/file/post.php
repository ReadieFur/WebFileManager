<?php
require_once __DIR__ . '/../request.php';
require_once __DIR__ . '/../_helpers/shareRequestHelper.php';

#region Request checks
Request::DenyIfNotRequestMethod(RequestMethod::POST);
Request::DenyIfDirectRequest(__FILE__);
#endregion

#region Parameter checks
if (!isset(Request::Post()['method']))
{ Request::SendError(400, ErrorMessages::INVALID_PARAMETERS); }
#endregion

switch (Request::Post()['method'])
{
    case 'add_share':
        ShareRequestHelper::AddShare(false);
    case 'update_share':
        ShareRequestHelper::UpdateShare(false);
    case 'get_share':
        ShareRequestHelper::GetShare(false);
    case 'delete_share':
        ShareRequestHelper::DeleteShare();
    default:
        Request::SendError(400, ErrorMessages::INVALID_PARAMETERS);
}