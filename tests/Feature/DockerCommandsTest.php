<?php

use App\Actions\CoolifyTask\RunRemoteProcess;
use App\Actions\CoolifyTask\TidyOutput;
use App\Models\User;
use App\Models\Server;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
uses(DatabaseMigrations::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
});

it('starts a docker container correctly', function () {


    test()->actingAs(User::factory([
        'uuid' => Str::uuid(),
        'email' => Str::uuid().'@example.com',
    ])->create());

    $coolifyNamePrefix = 'coolify_test_';


    $format = '{"ID":"{{ .ID }}", "Image": "{{ .Image }}", "Names":"{{ .Names }}"}';
    $areThereCoolifyTestContainers = "docker ps --filter=\"name={$coolifyNamePrefix}*\" --format '{$format}' ";

    // Generate a known name
    $containerName = 'coolify_test_' . now()->format('Ymd_his');
    $host = Server::where('name', 'testing-local-docker-container')->first();

    remoteProcess([
        "docker rm -f $(docker ps --filter='name={$coolifyNamePrefix}*' -aq) > /dev/null 2>&1"
    ], $host);

    // Assert there's no containers start with coolify_test_*
    $activity = remoteProcess([$areThereCoolifyTestContainers], $host);
    $tidyOutput = RunRemoteProcess::decodeOutput($activity);
    $containers = formatDockerCmdOutputToJson($tidyOutput);
    expect($containers)->toBeEmpty();

    // start a container nginx -d --name = $containerName
    $activity = remoteProcess(["docker run -d --rm --name {$containerName} nginx"], $host);
    expect($activity->getExtraProperty('exitCode'))->toBe(0);

    // docker ps name = $container
    $activity = remoteProcess([$areThereCoolifyTestContainers], $host);
    $tidyOutput = RunRemoteProcess::decodeOutput($activity);
    $containers = formatDockerCmdOutputToJson($tidyOutput);
    expect($containers->where('Names', $containerName)->count())->toBe(1);

    // Stop testing containers
    $activity = remoteProcess([
        "docker ps --filter='name={$coolifyNamePrefix}*' -aq && " .
        "docker rm -f $(docker ps --filter='name={$coolifyNamePrefix}*' -aq)"
    ], $host);
    expect($activity->getExtraProperty('exitCode'))->toBe(0);
});
