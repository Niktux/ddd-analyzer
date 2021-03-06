<?php

declare(strict_types = 1);

namespace Niktux\DDD\Analyzer\Domain\Visitors\Analyze;

use PhpParser\Node;
use PhpParser\Node\Stmt\Use_;
use Niktux\DDD\Analyzer\Domain\ContextualVisitor;
use PhpParser\Node\Name;
use Niktux\DDD\Analyzer\Domain\Defects\BoundedContextCoupling;
use Niktux\DDD\Analyzer\Domain\Defects\LayerViolationCall;
use Niktux\DDD\Analyzer\Domain\Services\NamespaceInterpreter;
use Niktux\DDD\Analyzer\Domain\ValueObjects\FullyQualifiedName;
use Niktux\DDD\Analyzer\Domain\ValueObjects\InterpretedFQN;
use Puzzle\Configuration;

class BoundedContextDependency extends ContextualVisitor
{
    private
        $interpreter,
        $bcWhitelist;

    public function __construct(NamespaceInterpreter $interpreter, Configuration $configuration)
    {
        parent::__construct();

        $this->interpreter = $interpreter;
        $this->bcWhitelist = $configuration->read('couplage/whitelist', []);
    }

    public function getId(): string
    {
        return "BoundedContextDependency";
    }

    public function enter(Node $node): void
    {
        if($node instanceof Use_)
        {
            if(! $this->inNamespace() || $this->interpreter->canTranslate($this->currentNamespace) === false)
            {
                return;
            }

            foreach($node->uses as $use)
            {
                $dependencyFqn = $this->analyze($use->name);

                if($dependencyFqn !== null)
                {
                    $this->checkRule($node, $dependencyFqn);
                }
            }
        }
    }

    private function analyze(Name $node): ?InterpretedFQN
    {
        $fqn = new FullyQualifiedName($node->toString());

        if($this->interpreter->canTranslate($fqn))
        {
            $fqn = $this->interpreter->translate($fqn);
            $bc = $fqn->boundedContext();

            return $fqn;
        }

        return null;
    }

    private function checkRule(Use_ $node, InterpretedFQN $dependency): void
    {
        $namespace = $this->interpreter->translate($this->currentNamespace);
        $targetBc = $dependency->boundedContext();

        if(! $namespace->boundedContext()->equals($targetBc))
        {
            if(! in_array($targetBc->value(), $this->bcWhitelist))
            {
                $this->dispatch(new BoundedContextCoupling($node, $namespace->boundedContext(), $dependency));
            }
        }

        if($namespace->layer() !== $dependency->layer())
        {
            if($namespace->layer()->isDeeperThan($dependency->layer()))
            {
                $this->dispatch(new LayerViolationCall($node, $namespace->layer(), $dependency));
            }
        }
    }
}
