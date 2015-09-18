<?php
namespace TYPO3\CMS\Form\Domain\Builder;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Form\Domain\Model\Element;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Builder for Element domain models.
 */
class ElementBuilder {

	/**
	 * @param FormBuilder $formBuilder
	 * @param Element $element
	 * @param array $userDefinedTypoScript
	 * @return ElementBuilder
	 */
	static public function create(FormBuilder $formBuilder, Element $element, array $userDefinedTypoScript) {
		/** @var ElementBuilder $elementBuilder */
		$elementBuilder = \TYPO3\CMS\Form\Bootstrap::getObjectManager()->get(ElementBuilder::class);
		$elementBuilder->setFormBuilder($formBuilder);
		$elementBuilder->setElement($element);
		$elementBuilder->setUserConfiguredElementTyposcript($userDefinedTypoScript);
		return $elementBuilder;
	}

	/**
	 * @var \TYPO3\CMS\Form\Utility\FormUtility
	 */
	protected $formUtility;

	/**
	 * @var \TYPO3\CMS\Form\Domain\Repository\TypoScriptRepository
	 */
	protected $typoScriptRepository;

	/**
	 * @var array
	 */
	protected $userConfiguredElementTyposcript = array();

	/**
	 * @var array
	 */
	protected $htmlAttributes = array();

	/**
	 * @var array
	 */
	protected $additionalArguments = array();

	/**
	 * @var array
	 */
	protected $wildcardPrefixes = array();

	/**
	 * @var FormBuilder
	 */
	protected $formBuilder;

	/**
	 * @var Element
	 */
	protected $element;

	/**
	 * @param \TYPO3\CMS\Form\Utility\FormUtility $formUtility
	 * @return void
	 */
	public function injectFormUtility(\TYPO3\CMS\Form\Utility\FormUtility $formUtility) {
		$this->formUtility = $formUtility;
	}

	/**
	 * @param \TYPO3\CMS\Form\Domain\Repository\TypoScriptRepository $typoScriptRepository
	 * @return void
	 */
	public function injectTypoScriptRepository(\TYPO3\CMS\Form\Domain\Repository\TypoScriptRepository $typoScriptRepository) {
		$this->typoScriptRepository = $typoScriptRepository;
	}

	/**
	 * @param FormBuilder $formBuilder
	 */
	public function setFormBuilder(FormBuilder $formBuilder) {
		$this->formBuilder = $formBuilder;
	}

	/**
	 * @param Element $element
	 */
	public function setElement(Element $element) {
		$this->element = $element;
	}

	/**
	 * Set the fluid partial path to the element
	 *
	 * @return void
	 */
	public function setPartialPaths() {
		$this->setElementPartialPath();
	}

	/**
	 * Set the fluid partial path to the element
	 *
	 * @return void
	 */
	protected function setElementPartialPath() {
		if (!isset($this->userConfiguredElementTyposcript['partialPath'])) {
			$partialPath = $this->typoScriptRepository->getDefaultFluidTemplate($this->element->getElementType());
		} else {
			$partialPath = $this->userConfiguredElementTyposcript['partialPath'];
			unset($this->userConfiguredElementTyposcript['partialPath']);
		}
		$this->element->setPartialPath($partialPath);
	}

	/**
	 * Set the fluid partial path to the element
	 *
	 * @return void
	 */
	public function setVisibility() {
		$visibility = FALSE;
		if ($this->formBuilder->getControllerAction() === 'show') {
			if (!isset($this->userConfiguredElementTyposcript['visibleInShowAction'])) {
				$visibility = (bool)$this->typoScriptRepository->getModelConfigurationByScope($this->element->getElementType(), 'visibleInShowAction');
			} else {
				$visibility = (bool)$this->userConfiguredElementTyposcript['visibleInShowAction'];
			}
		} else if ($this->formBuilder->getControllerAction() === 'confirmation') {
			if (!isset($this->userConfiguredElementTyposcript['visibleInConfirmationAction'])) {
				$visibility = (bool)$this->typoScriptRepository->getModelConfigurationByScope($this->element->getElementType(), 'visibleInConfirmationAction');
			} else {
				$visibility = (bool)$this->userConfiguredElementTyposcript['visibleInConfirmationAction'];
			}
		} else if ($this->formBuilder->getControllerAction() === 'process') {
			if (!isset($this->userConfiguredElementTyposcript['visibleInMail'])) {
				$visibility = (bool)$this->typoScriptRepository->getModelConfigurationByScope($this->element->getElementType(), 'visibleInMail');
			} else {
				$visibility = (bool)$this->userConfiguredElementTyposcript['visibleInMail'];
			}
		}
		$this->element->setShowElement($visibility);
	}

	/**
	 * Find all prefix-* attributes and return the
	 * found prefixs. Than delete them from the htmlAttributes array
	 *
	 * @return void
	 */
	public function setHtmlAttributeWildcards() {
		foreach ($this->htmlAttributes as $attributeName => $attributeValue) {
			if (strpos($attributeName, '-*') > 0) {
				$prefix = substr($attributeName, 0, -1);
				$this->wildcardPrefixes[] = $prefix;
				unset($this->htmlAttributes[$attributeName]);
			}
		}
	}

	/**
	 * Overlay user defined html attribute values
	 * To determine whats a html attribute, the htmlAttributes
	 * array is used. If a html attribute value is found in userConfiguredElementTyposcript
	 * this value is set to htmlAttributes and removed from userConfiguredElementTyposcript.
	 *
	 * @return void
	 */
	public function overlayUserdefinedHtmlAttributeValues() {
		foreach ($this->htmlAttributes as $attributeName => $attributeValue) {
			$attributeNameWithoutDot = rtrim($attributeName, '.');
			if (
				isset($this->userConfiguredElementTyposcript[$attributeNameWithoutDot])
				|| isset($this->userConfiguredElementTyposcript[$attributeNameWithoutDot . '.'])
			) {
				$returnValue = $this->renderAttributeValue($attributeName, array());
				$attributeValue = $returnValue['attributeValue'];
				$this->htmlAttributes[$attributeNameWithoutDot] = $attributeValue;
				unset($this->userConfiguredElementTyposcript[$attributeNameWithoutDot]);
			}
		}

			// the prefix-* magic
		$ignoreKeys = array();
		foreach ($this->userConfiguredElementTyposcript as $attributeName => $attributeValue) {
				// ignore child elements
			if (
				MathUtility::canBeInterpretedAsInteger($attributeName)
				|| isset($ignoreKeys[$attributeName])
			) {
				$ignoreKeys[$attributeName . '.'] = TRUE;
				continue;
			}

			foreach ($this->wildcardPrefixes as $wildcardPrefix) {
				if (strpos($attributeName, $wildcardPrefix) !== 0) {
					continue;
				}
				$attributeNameWithoutDot = rtrim($attributeName, '.');
				$returnValue = $this->renderAttributeValue($attributeName, $ignoreKeys);
				$attributeValue = $returnValue['attributeValue'];
				$ignoreKeys = $returnValue['ignoreKeys'];
				$this->htmlAttributes[$attributeNameWithoutDot] = $attributeValue;
				unset($this->userConfiguredElementTyposcript[$attributeNameWithoutDot]);
				break;
			}
		}

	}

	/**
	 * If fixedHtmlAttributeValues are defined for this element
	 * then overwrite the html attribute value
	 *
	 * @return void
	 */
	public function overlayFixedHtmlAttributeValues() {
		$fixedHtmlAttributeValues = $this->typoScriptRepository->getModelConfigurationByScope($this->element->getElementType(), 'fixedHtmlAttributeValues.');
		if (is_array($fixedHtmlAttributeValues)) {
			foreach ($fixedHtmlAttributeValues as $attributeName => $attributeValue) {
				$this->htmlAttributes[$attributeName] = $attributeValue;
			}
		}
	}

	/**
	 * Move htmlAttributes to additionalArguments that must be passed
	 * as a view helper argument
	 *
	 * @return void
	 */
	public function moveHtmlAttributesToAdditionalArguments() {
		$htmlAttributesUsedByTheViewHelperDirectly = $this->typoScriptRepository->getModelConfigurationByScope($this->element->getElementType(), 'htmlAttributesUsedByTheViewHelperDirectly.');
		if (is_array($htmlAttributesUsedByTheViewHelperDirectly)) {
			foreach ($htmlAttributesUsedByTheViewHelperDirectly as $attributeName) {
				if (array_key_exists($attributeName, $this->htmlAttributes)) {
					$this->additionalArguments[$attributeName] = $this->htmlAttributes[$attributeName];
					unset($this->htmlAttributes[$attributeName]);
				}
			}
		}
	}

	/**
	 * Set the viewhelper default arguments in the additionalArguments array
	 *
	 * @return void
	 */
	public function setViewHelperDefaulArgumentsToAdditionalArguments() {
		$viewHelperDefaulArguments = $this->typoScriptRepository->getModelConfigurationByScope($this->element->getElementType(), 'viewHelperDefaulArguments.');
		if (is_array($viewHelperDefaulArguments)) {
			foreach ($viewHelperDefaulArguments as $viewHelperDefaulArgumentName => $viewHelperDefaulArgumentValue) {
				$this->additionalArguments[$viewHelperDefaulArgumentName] = $viewHelperDefaulArgumentValue;
			}
		}
		unset($this->userConfiguredElementTyposcript['viewHelperDefaulArguments']);
	}

	/**
	 * Move all userdefined properties to the additionalArguments
	 * array. Ignore the child elements
	 *
	 * @return void
	 */
	public function moveAllOtherUserdefinedPropertiesToAdditionalArguments() {
		$ignoreKeys = array();
		foreach ($this->userConfiguredElementTyposcript as $attributeName => $attributeValue) {
				// ignore child elements
			if (
				MathUtility::canBeInterpretedAsInteger($attributeName)
				|| isset($ignoreKeys[$attributeName])
				|| $attributeName == 'postProcessor.'
				|| $attributeName == 'rules.'
				|| $attributeName == 'filters.'
				|| $attributeName == 'layout'
			) {
				$ignoreKeys[$attributeName . '.'] = TRUE;
				continue;
			}

			if ($this->formBuilder->getConfiguration()->getCompatibility()) {
				$returnValue = $this->formBuilder->getCompatibilityService()->remapOldAttributes(
					$this->element->getElementType(),
					$attributeName,
					$this->additionalArguments,
					$this->userConfiguredElementTyposcript
				);
				$attributeName = $returnValue['attributeName'];
				$this->additionalArguments = $returnValue['additionalArguments'];
				$this->userConfiguredElementTyposcript = $returnValue['userConfiguredElementTyposcript'];
			}

			$attributeNameWithoutDot = rtrim($attributeName, '.');
			$returnValue = $this->renderAttributeValue($attributeName, $ignoreKeys);
			$attributeValue = $returnValue['attributeValue'];
			$ignoreKeys = $returnValue['ignoreKeys'];
			$this->additionalArguments[$attributeNameWithoutDot] = $attributeValue;
			unset($this->userConfiguredElementTyposcript[$attributeNameWithoutDot]);
		}
			// remove "stdWrap." from "additionalArguments" on
			// the FORM Element
		if (
			!$this->formBuilder->getConfiguration()->getContentElementRendering()
			&& $this->element->getElementType() == 'FORM'
		) {
			unset($this->additionalArguments['stdWrap']);
			unset($this->additionalArguments['stdWrap.']);
		}
	}

	/**
	 * Set the name and id attribute
	 *
	 * @return array
	 */
	public function setNameAndId() {
		if (
			$this->element->getParentElement()
			&& (int)$this->typoScriptRepository->getModelConfigurationByScope($this->element->getParentElement()->getElementType(), 'childsInerhitName') == 1
		) {
			$this->htmlAttributes['name'] = $this->element->getParentElement()->getName();
			$this->htmlAttributes['multiple'] = '1';
			$name = $this->sanitizeNameAttribute($this->userConfiguredElementTyposcript['name']);
			$this->element->setName($name);
		} else {
			$this->htmlAttributes['name'] = $this->sanitizeNameAttribute($this->htmlAttributes['name']);
			$this->element->setName($this->htmlAttributes['name']);
		}
		$this->htmlAttributes['id'] = $this->sanitizeIdAttribute($this->htmlAttributes['id']);
		$this->element->setId($this->htmlAttributes['id']);
	}

	/**
	 * Render a attribute value
	 * Try to render it as content element if allowed
	 * Take care about short synthax like label.data = LLL:EXT: ...
	 * Try to translate label.data = LLL: ... stuff even if content
	 * elemet rendering is disabled
	 *
	 * @param string $attributeName
	 * @param array $ignoreKeys
	 * @return string
	 */
	protected function renderAttributeValue($attributeName = '', array $ignoreKeys) {
		$attributeNameWithoutDot = rtrim($attributeName, '.');
		if (
			$this->formBuilder->getConfiguration()->getContentElementRendering()
			&& isset($this->userConfiguredElementTyposcript[$attributeNameWithoutDot . '.'])
		) {
			if ($attributeName !== $attributeNameWithoutDot) {
				$this->userConfiguredElementTyposcript[$attributeNameWithoutDot] = 'TEXT';
			}
			$attributeValue = $this->formUtility->renderContentObject(
				$this->userConfiguredElementTyposcript[$attributeNameWithoutDot],
				$this->userConfiguredElementTyposcript[$attributeNameWithoutDot . '.']
			);
			$ignoreKeys[$attributeNameWithoutDot . '.'] = TRUE;
			unset($this->userConfiguredElementTyposcript[$attributeNameWithoutDot . '.']);
		} else {
			if (isset($this->userConfiguredElementTyposcript[$attributeName]['value'])) {
				$attributeValue = $this->userConfiguredElementTyposcript[$attributeName]['value'];
			} elseif (isset($this->userConfiguredElementTyposcript[$attributeName]['data'])) {
				$attributeValue = LocalizationUtility::translate($this->userConfiguredElementTyposcript[$attributeName]['data'], 'form');
			} else {
				$attributeValue = $this->userConfiguredElementTyposcript[$attributeNameWithoutDot];
			}
		}
		return array(
			'attributeValue' => $attributeValue,
			'ignoreKeys' => $ignoreKeys,
		);
	}

	/**
	 * If the name is not defined it is automatically generated
	 * using the following syntax: id-{element_counter}
	 * The name attribute will be transformed if it contains some
	 * non allowed characters:
	 * - spaces are changed into hyphens
	 * - remove all characters except a-z A-Z 0-9 _ -
	 *
	 * @param string $name
	 * @return string
	 */
	public function sanitizeNameAttribute($name) {
		$name = $this->formUtility->sanitizeNameAttribute($name);
		if (empty($name)) {
			$name = 'id-' . $this->element->getElementCounter();
		}
		return $name;
	}

	/**
	 * If the id is not defined it is automatically generated
	 * using the following syntax: field-{element_counter}
	 * The id attribute will be transformed if it contains some
	 * non allowed characters:
	 * - spaces are changed into hyphens
	 * - if the id start with a integer then transform it to field-{integer}
	 * - remove all characters expect a-z A-Z 0-9 _ - : .
	 *
	 * @param string $id
	 * @return string
	 */
	protected function sanitizeIdAttribute($id) {
		$id = $this->formUtility->sanitizeIdAttribute($id);
		if (empty($id)) {
			$id = 'field-' . $this->element->getElementCounter();
		}
		return $id;
	}

	/**
	 * Get the current html attributes
	 *
	 * @return array
	 */
	public function getHtmlAttributes() {
		return $this->htmlAttributes;
	}

	/**
	 * Set the current html attributes
	 *
	 * @param array $htmlAttributes
	 */
	public function setHtmlAttributes(array $htmlAttributes) {
		$this->htmlAttributes = $htmlAttributes;
	}

	/**
	 * Get the current additional arguments
	 *
	 * @return array
	 */
	public function getAdditionalArguments() {
		return $this->additionalArguments;
	}

	/**
	 * Set the current additional arguments
	 *
	 * @param array $additionalArguments
	 */
	public function setAdditionalArguments(array $additionalArguments) {
		$this->additionalArguments = $additionalArguments;
	}

	/**
	 * Get the current wildcard prefixes
	 *
	 * @return array
	 */
	public function getWildcardPrefixes() {
		return $this->wildcardPrefixes;
	}

	/**
	 * Set the current wildcard prefixes
	 *
	 * @param array $wildcardPrefixes
	 */
	public function setWildcardPrefixes(array $wildcardPrefixes) {
		$this->wildcardPrefixes = $wildcardPrefixes;
	}

	/**
	 * Get the current Element
	 *
	 * @return array
	 */
	public function getUserConfiguredElementTyposcript() {
		return $this->userConfiguredElementTyposcript;
	}

	/**
	 * Set the current Element
	 *
	 * @param array $userConfiguredElementTyposcript
	 */
	public function setUserConfiguredElementTyposcript(array $userConfiguredElementTyposcript) {
		$this->userConfiguredElementTyposcript = $userConfiguredElementTyposcript;
	}

}
