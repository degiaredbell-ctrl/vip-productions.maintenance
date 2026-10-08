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
$start = strpos($html,'data-page="');
$s = $start + strlen('data-page="');
$e = strpos($html,'"',$s);
$raw = substr($html,$s,$e-$s);
$raw = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');
$page = json_decode($raw,true);
var_dump($page['props']['types'], count($page['props']['templates']), $page['props']['categories'], count($page['props']['trashed']));
