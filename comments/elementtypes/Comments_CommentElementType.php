<?php
namespace Craft;

class Comments_CommentElementType extends BaseElementType
{
	public function getName()
	{
		return Craft::t('Comment');
	}

	public function hasContent()
	{
		return true;
	}

	public function hasTitles()
	{
		return false;
	}

    public function hasStatuses()
    {
        return true;
    }

	public function getStatuses()
	{
		return array(
			Comments_CommentModel::APPROVED => Craft::t('Approved'),
			Comments_CommentModel::PENDING => Craft::t('Pending'),
			Comments_CommentModel::SPAM => Craft::t('Spam'),
			Comments_CommentModel::TRASHED => Craft::t('Trashed')
		);
	}

    public function getSources($context = null)
    {
        $sources = array(
        	'*' => array('label' => Craft::t('All Comments')),
        	'entries' => array('heading' => Craft::t('Entries')),
        );

		foreach (craft()->comments->getEntriesWithComments() as $entry) {
			$key = 'entry:'.$entry->id;

			$sources[$key] = array(
				'label'    => $entry->title,
				'criteria' => array('entryId' => $entry->id),
			);
		}

		return $sources;
    }

    public function populateElementModel($row)
    {
        return Comments_CommentModel::populateModel($row);
    }

    public function defineTableAttributes($source = null)
    {
        return array(
            'id'			=> Craft::t(''),
            'comment'		=> Craft::t('Comment'),
            'dateCreated' 	=> Craft::t('Created'),
            'entry' 		=> Craft::t('Entry'),
        );
    }

    public function defineSortableAttributes()
    {
        return array(
            'dateCreated' 	=> Craft::t('Created'),
            'comment'		=> Craft::t('Comment'),
            'entry' 		=> Craft::t('Entry'),
        );
    }

    public function getTableAttributeHtml(BaseElementModel $element, $attribute)
    {
        switch ($attribute) {
            case 'user': {
                $user = craft()->users->getUserById($element->userId);

                if ($user == null) {
                    return $element->name;
                } else {
                    $url = UrlHelper::getCpUrl('users/' . $user->id);
                    return "<a href='" . $url . "'>" . $user->getFriendlyName() . "</a>";
                }
            }
            case 'entry': {
                $entry = craft()->entries->getEntryById($element->entryId);

                if ($entry == null) {
                    return Craft::t('[Deleted Entry]');
                } else {
                    return "<a href='" . $entry->cpEditUrl . "'>" . $entry->title . "</a>";
                }
            }
            case 'comment': {
                $user = craft()->users->getUserById($element->userId);

                if ($user == null) {
                    $userName = $element->name;
                } else {
                    $url = UrlHelper::getCpUrl('users/' . $user->id);
                    $userName = $user->getFriendlyName();
                }

                $html = '<div class="comment-block">';
                $html .= '<span class="status '.$element->status.'"></span>';
            	$html .= '<a href="' . $element->getCpEditUrl() . '">';
            	$html .= '<span class="username">' . $userName . '</span>';
            	$html .= '<small>' . $element->getExcerpt(0, 100) . '</small></a>';
            	$html .= '</div>';
            	return $html;
            }
            default: {
				return parent::getTableAttributeHtml($element, $attribute);
            }
        }
    }

    public function defineCriteriaAttributes()
    {
        return array(
			'entryId'		=> array(AttributeType::Number),
			'userId'		=> array(AttributeType::Number),
			'structureId'	=> array(AttributeType::Number),
			'status'		=> array(AttributeType::String),
			'name'			=> array(AttributeType::String),
			'email'			=> array(AttributeType::Email),
			'url'			=> array(AttributeType::Url),
			'ipAddress'		=> array(AttributeType::String),
			'userAgent'		=> array(AttributeType::String),
			'comment'		=> array(AttributeType::Mixed),
			'order'			=> array(AttributeType::String, 'default' => 'lft, commentDate desc'),
        );
    }

	public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
	{
        $query
		->addSelect('comments.entryId, comments.userId, comments.structureId, comments.status, comments.name, comments.email, comments.url, comments.ipAddress, comments.userAgent, comments.comment, comments.dateCreated AS commentDate')
		->join('comments comments', 'comments.id = elements.id')
		->leftJoin('structures structures', 'structures.id = comments.structureId')
		->leftJoin('structureelements structureelements', array('and', 'structureelements.structureId = structures.id', 'structureelements.elementId = comments.id'));

		if ($criteria->entryId) {
			$query->andWhere(DbHelper::parseParam('comments.entryId', $criteria->entryId, $query->params));
		}

		if ($criteria->userId) {
			$query->andWhere(DbHelper::parseParam('comments.userId', $criteria->userId, $query->params));
		}

		if ($criteria->structureId) {
			$query->andWhere(DbHelper::parseParam('comments.structureId', $criteria->structureId, $query->params));
		}

		if ($criteria->status) {
			$query->andWhere(DbHelper::parseParam('comments.status', $criteria->status, $query->params));
		}

		if ($criteria->name) {
			$query->andWhere(DbHelper::parseParam('comments.name', $criteria->name, $query->params));
		}

		if ($criteria->email) {
			$query->andWhere(DbHelper::parseParam('comments.email', $criteria->email, $query->params));
		}

		if ($criteria->url) {
			$query->andWhere(DbHelper::parseParam('comments.url', $criteria->url, $query->params));
		}

		if ($criteria->ipAddress) {
			$query->andWhere(DbHelper::parseParam('comments.ipAddress', $criteria->ipAddress, $query->params));
		}

		if ($criteria->userAgent) {
			$query->andWhere(DbHelper::parseParam('comments.userAgent', $criteria->userAgent, $query->params));
		}

		if ($criteria->comment) {
			$query->andWhere(DbHelper::parseParam('comments.comment', $criteria->comment, $query->params));
		}

		if ($criteria->dateCreated) {
			$query->andWhere(DbHelper::parseDateParam('comments.dateCreated', $criteria->dateCreated, $query->params));
		}

	}
	
	public function getAvailableActions($source = null)
	{
		return array('Comments_Status');
	}


}