<?php
/**
 * Copyright 2016 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Cloud\Dev\Split\Command;

use Google\Cloud\Dev\Command\GoogleCloudCommand;
use Google\Cloud\Dev\GetComponentsTrait;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use vierbergenlars\SemVer\SemVerException;
use vierbergenlars\SemVer\version;

class Split extends GoogleCloudCommand
{
    use GetComponentsTrait;

    const TOKEN_ENV = 'GH_OAUTH_TOKEN';
    const TAG_ENV = 'TRAVIS_TAG';
    const TARGET_REGEX = '/([a-zA-Z0-9-_]{1,})\/([a-zA-Z0-9-_]{1,})\.git/';

    const COMPONENT_BASE = '%s/';
    const SPLIT_SHELL = '%s/dev/sh/split';
    const PATH_MANIFEST = '%s/docs/manifest.json';
    const PARENT_TAG_NAME = 'https://github.com/GoogleCloudPlatform/google-cloud-php/releases/tag/%s';

    const GITHUB_RELEASES_ENDPOINT = 'https://api.github.com/repos/%s/%s/releases/tags/%s';
    const GITHUB_RELEASE_CREATE_ENDPOINT = 'https://api.github.com/repos/%s/%s/releases';

    private $splitShell;

    private $components;

    private $manifest;

    private $http;

    private $token;

    public function __construct($rootPath)
    {
        $this->rootPath = realpath($rootPath);
        $this->splitShell = sprintf(self::SPLIT_SHELL, $rootPath);
        $this->components = sprintf(self::COMPONENT_BASE, $rootPath);
        $this->manifest = sprintf(self::PATH_MANIFEST, $rootPath);

        $this->http = new Client;
        $this->token = getenv(self::TOKEN_ENV);

        parent::__construct($rootPath);
    }

    protected function configure()
    {
        $this->setName('split')
            ->setDescription('Split subtree and push to various remotes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!getenv(self::TAG_ENV)) {
            $output->writeln('This command should only be run inside a CI post-release process');
            return;
        }

        $components = $this->getComponents($this->rootPath, $this->components);

        $tag = getenv(self::TAG_ENV);

        $parentTagSource = sprintf(self::PARENT_TAG_NAME, $tag);

        $errors = [];
        foreach ($components as $component) {
            $output->writeln('');
            $output->writeln(sprintf('<info>Starting on component %s</info>', $component['id']));
            $output->writeln(sprintf('Using target %s', $component['target']));
            $output->writeln('------------');
            exec(sprintf(
                '%s %s %s',
                $this->splitShell,
                $component['prefix'],
                $component['target']
            ), $shellOutput, $return);

            echo implode(PHP_EOL, $shellOutput);
            if ($return !== 0) {
                $errors[] = $component['id'] .': splitsh or git push failure';
                continue;
            }

            $target = $component['target'];
            $matches = [];
            preg_match(self::TARGET_REGEX, $target, $matches);

            $org = $matches[1];
            $repo = $matches[2];

            $version = $this->getComponentVersion($this->manifest, $component['id']);
            try {
                new version($version);
            } catch (SemVerException $e) {
                $output->writeln(sprintf(
                    'Component %s version %s is invalid.',
                    $component['id'],
                    $version
                ));

                $errors[] = $component['id'] .': given version invalid.';
                continue;
            }

            if ($this->doesTagExist($version, $org, $repo)) {
                $output->writeln(sprintf(
                    'Component %s already tagged at version %s',
                    $component['id'],
                    $version
                ));
                continue;
            }

            $name = $component['displayName'] .' '. $version;
            $notes = sprintf(
                'For release notes, please see the [associated Google Cloud PHP release](%s).',
                $parentTagSource
            );
            $this->createRelease($version, $org, $repo, $name, $notes);

            $output->writeln(sprintf(
                'Release %s created for component %s',
                $version,
                $component['id']
            ));
        }

        if ($errors) {
            $output->writeln(PHP_EOL . '<error>One or more errors occurred!</error>');
            $output->writeln('- ' . implode(PHP_EOL .'- ', $errors));
            return 1;
        }
    }

    private function doesTagExist($tagName, $org, $repo)
    {
        $res = $this->http->get(sprintf(
            self::GITHUB_RELEASES_ENDPOINT,
            $org, $repo, $tagName
        ), [
            'http_errors' => false,
            'auth' => [null, $this->token]
        ]);

        return ($res->getStatusCode() === 200);
    }

    private function createRelease($tagName, $org, $repo, $name, $notes)
    {
        $requestBody = [
            'tag_name' => $tagName,
            'name' => $name,
            'body' => $notes
        ];

        $res = $this->http->post(sprintf(
            self::GITHUB_RELEASE_CREATE_ENDPOINT,
            $org, $repo
        ), [
            'http_errors' => false,
            'json' => $requestBody,
            'auth' => [null, $this->token]
        ]);
    }
}
