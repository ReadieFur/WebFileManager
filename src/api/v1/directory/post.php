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
    case 'add_root':
        ShareRequestHelper::AddRoot(true);
    case 'update_root':
        ShareRequestHelper::UpdateRoot(true);
    case 'get_roots':
        ShareRequestHelper::GetRoots();
    case 'delete_root':
        ShareRequestHelper::DeleteRoot();
    case 'add_share':
        ShareRequestHelper::AddShare(true);
    case 'update_share':
        ShareRequestHelper::UpdateShare(true);
    case 'get_share':
        ShareRequestHelper::GetShare(true);
    case 'delete_share':
        ShareRequestHelper::DeleteShare();
    default:
        Request::SendError(400, ErrorMessages::INVALID_PARAMETERS);
}