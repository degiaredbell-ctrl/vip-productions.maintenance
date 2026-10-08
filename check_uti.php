<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$u = App\Models\User::where('email','admin.pm_anwar@redbellgroup.com')->first();
$req = Illuminate\Http\Request::create('/utility');
$req->setUserResolver(fn() => $u);
$c = new App\Http\Controllers\UtilityController();
$r = $c->index($req);
$resp = $r->toResponse($req);
$html = $resp->getContent();
if(strpos($html,'data-page')===false){ echo 'no'; exit; }
$start = strpos($html,'data-page="');
if($start===false){ echo 'no2'; exit; }
$s = $start + strlen('data-page="');
$e = strpos($html,'"',$s);
if($e===false){ echo 'no3'; exit; }
$raw = substr($html,$s,$e-$s);
$raw = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');
$page = json_decode($raw,true);
print_r(array_keys($page['props']));
