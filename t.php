<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$u=App\Models\User::where('email','admin.pm_anwar@redbellgroup.com')->first();
$req=Illuminate\Http\Request::create('/utility');
$req->setUserResolver(fn()=>$u);
$c=new App\Http\Controllers\UtilityController();
$r=$c->index($req);
$resp=$r->toResponse($req);
$html=$resp->getContent();
if(preg_match('/data-page="([^"]+)"/',$html,$m)){
  $raw=html_entity_decode($m[1],ENT_QUOTES,'UTF-8');
  $page=json_decode($raw,true);
  var_dump(count($page['props']['templates']),count($page['props']['categories']),count($page['props']['trashed']),$page['props']['types'][0]['value']??'?');
}
