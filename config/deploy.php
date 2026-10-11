<?php

/*
|--------------------------------------------------------------------------
| Deploy hook for web space without a command line
|--------------------------------------------------------------------------
|
| deploy.sh uploads Cardbox via FTP and then calls POST /api/v1/deploy to run
| the database migrations. The route only exists when DEPLOY_HOOK_TOKEN is
| set (at least 32 characters); otherwise it answers 404. On servers with SSH
| or in a container leave it empty — migrations run there from the shell.
|
*/

return [

    'hook_token' => env('DEPLOY_HOOK_TOKEN'),

];
