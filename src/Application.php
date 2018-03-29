<?php

declare(strict_types = 1);

namespace Niktux\DDD\Analyzer;

use Silex\Provider\SessionServiceProvider;
use Onyx\Providers;
use Gaufrette\Adapter\Local;
use Gaufrette\Filesystem;
use Puzzle\PrefixedConfiguration;
use Niktux\DDD\Analyzer\Domain\Analyzer;
use Niktux\DDD\Analyzer\Domain\ValueObjects\TraverseMode;
use Niktux\DDD\Analyzer\Domain\DefectSorter;
use Niktux\DDD\Analyzer\Domain\Services\NamespaceInterpreter;
use Niktux\DDD\Analyzer\Domain\Services\KnowledgeBase;
use Niktux\DDD\Analyzer\Domain\Visitors\RawCollect\TypeCollector;
use Niktux\DDD\Analyzer\Domain\Visitors\Infer\TypeInference;
use Niktux\DDD\Analyzer\Domain\Visitors\Collect\BoundedContextCollector;
use Niktux\DDD\Analyzer\Domain\Visitors\Collect\CQSCollector;
use Niktux\DDD\Analyzer\Domain\Visitors\Analyze\ClassAliasingDetection;
use Niktux\DDD\Analyzer\Domain\Visitors\Analyze\AnonymousClassDetection;
use Niktux\DDD\Analyzer\Domain\Visitors\Analyze\BoundedContextDependency;
use PhpParser\NodeVisitor\NameResolver;
use Niktux\DDD\Analyzer\Domain\Visitors\PhpParserVisitorAdapter;
use Niktux\DDD\Analyzer\Domain\Visitors\Analyze\ReturnType;

class Application extends \Onyx\Application
{
    protected function registerProviders(): void
    {
        $this->register(new SessionServiceProvider());
        $this->register(new Providers\Monolog([
            // insert your loggers here
        ]));
        $this->register(new Providers\Twig());
        $this->register(new Providers\Webpack());
    }

    protected function initializeServices(): void
    {
        $this->configureTwig();

        $this->initializeFilesystem();
        $this->initializeSubscribers();

        $this['event.dispatcher'] = function($c) {
            return new Dispatchers\EventDispatcher($c['dispatcher']);
        };

        $this['name.interpreter'] = function($c) {
            return new NamespaceInterpreter(2);
        };

        $this['defect.sorter'] = function($c) {
            return new DefectSorter($c['name.interpreter']);
        };

        $this['knowledgeBase'] = function($c) {
            return new KnowledgeBase();
        };

        $this['analyzer'] = function($c) {
            $config = $c['configuration'];
            $analyzer = new Analyzer($c['event.dispatcher'], $c['filesystem']);

            if($config->read('analyzer/skipTests', false))
            {
                $analyzer->skipTests();
            }

            $visitors = [
                'BoundedContextDependency',
                'AnonymousClassDetection',
                'ClassAliasingDetection',
                'ReturnType',
            ];

            foreach($visitors as $visitor)
            {
                if($config->read("analyzer/$visitor/enabled", true))
                {
                    $analyzer->addVisitor(TraverseMode::analyze(), $c["visitors.analyze.$visitor"]);
                }
            }

            $analyzer->addVisitors(
                TraverseMode::complete(),
                [
                    $c["visitors.complete.nameResolver"],
                ]
            );

            $analyzer->addVisitors(
                TraverseMode::rawCollect(),
                [
                    $c["visitors.rawCollect.typeCollector"],
                ]
            );

            $analyzer->addVisitors(
                TraverseMode::infer(),
                [
                    $c["visitors.infer.typeInference"],
                ]
            );

            $analyzer->addVisitors(
                TraverseMode::collect(),
                [
                    $c["visitors.collect.BoundedContextCollector"],
                    $c["visitors.collect.CQSCollector"],
                ]
            );

            return $analyzer;
        };

        $this->initializeVisitors();
    }

    private function initializeVisitors(): void
    {
        $this->initializeCompleteVisitors();
        $this->initializeRawCollectVisitors();
        $this->initializeInferVisitors();
        $this->initializeCollectVisitors();
        $this->initializeAnalyzeVisitors();
    }

    private function initializeCompleteVisitors(): void
    {
        $this['visitors.complete.nameResolver'] = function($c) {
            return new PhpParserVisitorAdapter(new NameResolver());
        };
    }

    private function initializeRawCollectVisitors(): void
    {
        $this['visitors.rawCollect.typeCollector'] = function($c) {
            return new TypeCollector($c['knowledgeBase']);
        };
    }

    private function initializeCollectVisitors(): void
    {
        $this['visitors.collect.BoundedContextCollector'] = function($c) {
            return new BoundedContextCollector($c['knowledgeBase'], $c['name.interpreter']);
        };

        $this['visitors.collect.CQSCollector'] = function($c) {
            $config = new PrefixedConfiguration($c['configuration'], "analyzer/CQSCollector");

            return new CQSCollector(
                $c['knowledgeBase'],
                $c['name.interpreter'],
                $config->readRequired('interfaces/query'),
                $config->readRequired('interfaces/command')
            );
        };
    }

    private function initializeInferVisitors(): void
    {
        $this['visitors.infer.typeInference'] = function($c) {
            return new TypeInference($c['knowledgeBase']);
        };
    }

    private function initializeAnalyzeVisitors(): void
    {
        $this['visitors.analyze.BoundedContextDependency'] = function($c) {
            return new BoundedContextDependency(
                $c['name.interpreter'],
                new PrefixedConfiguration($c['configuration'], "analyzer/BoundedContextDependency")
            );
        };

        $this['visitors.analyze.AnonymousClassDetection'] = function($c) {
            return new AnonymousClassDetection();
        };

        $this['visitors.analyze.ReturnType'] = function($c) {
            return new ReturnType(
                new PrefixedConfiguration($c['configuration'], "analyzer/ReturnType"),
                $c['knowledgeBase']
            );
        };

        $this['visitors.analyze.ClassAliasingDetection'] = function($c) {
            return new ClassAliasingDetection(
                new PrefixedConfiguration($c['configuration'], "analyzer/ClassAliasingDetection")
            );
        };
    }

    private function initializeFilesystem(): void
    {
        $this['filesystem.path'] = 'src/';
        $this['filesystem.adapter'] = function($c) {
            return new Local($c['filesystem.path']);
        };

        $this['filesystem'] = function($c) {
            return new Filesystem($c['filesystem.adapter']);
        };
    }

    private function initializeSubscribers(): void
    {
        $this['subscriber.console'] = function($c) {
            return new EventSubscribers\Console();
        };

        $this['reporter.html'] = function($c) {
            return new Reporters\HTMLReporter($c['twig']);
        };

        $this['subscriber.html'] = function($c) {
            return new EventSubscribers\HTML($c['reporter.html'], $c['defect.sorter']);
        };

        $this['subscriber.json'] = function($c) {
            return new EventSubscribers\Json($c['knowledgeBase']);
        };
    }

    private function configureTwig(): void
    {
        $this['view.manager']->addPath(array(
            $this['root.path'] . 'views/',
        ));
    }

    protected function mountControllerProviders(): void
    {
        $this->mount('/', new Controllers\Home\Provider());
        $this->mount('/api/', new Controllers\Api\Provider());
    }
}
