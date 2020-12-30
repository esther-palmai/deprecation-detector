<?php

namespace SensioLabs\DeprecationDetector\Visitor\Deprecation;

use phpDocumentor\Reflection\DocBlockFactory;
use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use SensioLabs\DeprecationDetector\FileInfo\Deprecation\ClassDeprecation;
use SensioLabs\DeprecationDetector\FileInfo\Deprecation\FunctionDeprecation;
use SensioLabs\DeprecationDetector\FileInfo\Deprecation\InterfaceDeprecation;
use SensioLabs\DeprecationDetector\FileInfo\Deprecation\MethodDeprecation;
use SensioLabs\DeprecationDetector\FileInfo\PhpFileInfo;
use SensioLabs\DeprecationDetector\Visitor\DeprecationVisitorInterface;

class FindDeprecatedTagsVisitor extends NodeVisitorAbstract implements DeprecationVisitorInterface
{
    /**
     * @var string
     */
    protected $parentName;

    /**
     * @var PhpFileInfo
     */
    protected $phpFileInfo;

    /**
     * @var DocBlockFactory
     */
    protected $docBlockFactory;

    /**
     * @param PhpFileInfo $phpFileInfo
     *
     * @return $this
     */
    public function setPhpFileInfo(PhpFileInfo $phpFileInfo)
    {
        $this->phpFileInfo = $phpFileInfo;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassLike) {
            if ($node instanceof Node\Stmt\Class_ && $node->isAnonymous()) {
                return;
            }

            $this->parentName = $node->namespacedName->toString();
        }

        $this->docBlockFactory = DocBlockFactory::createInstance();

        if (!$this->hasDeprecatedDocComment($node)) {
            return;
        }

        if ($node instanceof Node\Stmt\Function_) {
            $this->phpFileInfo->addFunctionDeprecation(
                new FunctionDeprecation($node->name, $this->getDeprecatedDocComment($node))
            );

            return;
        }

        if ($node instanceof Node\Stmt\Class_) {
            $this->phpFileInfo->addClassDeprecation(
                new ClassDeprecation($this->parentName, $this->getDeprecatedDocComment($node))
            );

            return;
        }

        if ($node instanceof Node\Stmt\Interface_) {
            $this->phpFileInfo->addInterfaceDeprecation(
                new InterfaceDeprecation($this->parentName, $this->getDeprecatedDocComment($node))
            );

            return;
        }

        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->phpFileInfo->addMethodDeprecation(
                new MethodDeprecation($this->parentName, $node->name, $this->getDeprecatedDocComment($node))
            );

            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassLike) {
            $this->parentName = null;
        }
    }

    /**
     * @param Node $node
     *
     * @return bool
     */
    protected function hasDeprecatedDocComment(Node $node)
    {
        try {
            $docBlock = $this->docBlockFactory->create($node);

            return count($docBlock->getTagsByName('deprecated')) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param Node $node
     *
     * @return null|string
     */
    protected function getDeprecatedDocComment(Node $node)
    {
        try {
            $docBlock = $this->docBlockFactory->create($node);
            $deprecatedTag = $docBlock->getTagsByName('deprecated');

            if (0 === count($deprecatedTag)) {
                return null;
            }

            $comment = $deprecatedTag[0]->getContent();

            return preg_replace('/[[:blank:]]+/', ' ', $comment);
        } catch (\Exception $e) {
            return null;
        }
    }
}
