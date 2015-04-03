<?php
namespace Craft;

class CommentsService extends BaseApplicationComponent
{
    private $_commentsById;
    private $_fetchedAllComments = false;

    public function getCriteria(array $attributes = array())
    {
        return craft()->elements->getCriteria('Comments_Comment', $attributes);
    }

    public function getAllComments()
    {
        $attributes = array('order' => 'dateCreated');

        return $this->getCriteria($attributes)->find();
    }

    public function getCommentById($commentId)
    {
        return $this->getCriteria(array('limit' => 1, 'id' => $commentId))->first();
    }

    public function getCommentModels($criteria = null)
    {
        if (!$this->_fetchedAllComments) {
            $records = Comments_CommentRecord::model()->ordered()->findAll();
            $this->_commentsById = Comments_CommentModel::populateModels($records, 'id');
            $this->_fetchedAllComments = true;
        }

        return array_values($this->_commentsById);
    }

    public function getElementsWithComments()
    {
        $criteria = new \CDbCriteria();
        $criteria->group = 'elementId';

        $comments = Comments_CommentRecord::model()->findAll($criteria);

        $entries = array();
        foreach ($comments as $comment) {
            $element = craft()->elements->getElementById($comment->elementId);
            $entries[] = $element;
        }

        return $entries;
    }

    public function getStructureId()
    {
        return craft()->plugins->getPlugin('comments')->getSettings()->structureId;
    }

    public function saveComment(Comments_CommentModel $comment)
    {
        $isNewComment = !$comment->id;

        // Check for parent Comments
        $hasNewParent = $this->_checkForNewParent($comment);

        if ($hasNewParent) {
            if ($comment->parentId) {
                $parentComment = $this->getCommentById($comment->parentId);

                if (!$parentComment) {
                    throw new Exception(Craft::t('No comment exists with the ID “{id}”.', array('id' => $comment->parentId)));
                }
            } else {
                $parentComment = null;
            }

            $comment->setParent($parentComment);
        }

        // Get the comment record
        if (!$isNewComment) {
            $commentRecord = Comments_CommentRecord::model()->findById($comment->id);

            if (!$commentRecord) {
                throw new Exception(Craft::t('No comment exists with the ID “{id}”.', array('id' => $comment->id)));
            }
        } else {
            $commentRecord = new Comments_CommentRecord();
        }


        // Load in all the attributes from the Comment model into this record
        $commentRecord->setAttributes($comment->getAttributes(), false);


        // Fire an 'onBeforeSave' event
        Craft::import('plugins.comments.events.CommentsEvent');
        $event = new CommentsEvent($this, array('comment' => $comment));
        craft()->comments->onBeforeSave($event);


        // Now, lets try to save all this
        if ($comment->validate()) {
            $success = craft()->elements->saveElement($comment);

            if (!$success) {
                return array('error' => $comment->getErrors());
            }

            // Now that we have an element ID, save it on the other stuff
            if ($isNewComment) {
                $commentRecord->id = $comment->id;
            }

            // Save the actual comment
            $commentRecord->save(false);

            // Has the parent changed?
            if ($hasNewParent) {
                if (!$comment->parentId) {
                    craft()->structures->appendToRoot($this->getStructureId(), $comment);
                } else {
                    craft()->structures->append($this->getStructureId(), $comment, $parentComment);
                }
            }

            return $comment;
        } else {
            $comment->addErrors($commentRecord->getErrors());
            return array('error' => $comment->getErrors());
        }
    }

    public function redirect($object = null)
    {
        $url = craft()->request->getPost('redirect');

        if ($url === null) {
            $url = craft()->request->getParam('return');

            if ($url === null) {
                $url = craft()->request->getUrlReferrer();

                if ($url === null) {
                    $url = '/';
                }
            }
        }

        if ($object) {
            $url = craft()->templates->renderObjectTemplate($url, $object);
        }

        craft()->request->redirect($url);
    }











    private function _checkForNewParent(Comments_CommentModel $comment)
    {
        // Is it a brand new comment?
        if (!$comment->id) {
            return true;
        }

        // Was a parentId actually submitted?
        if ($comment->parentId === null) {
            return false;
        }

        // Is it set to the top level now, but it hadn't been before?
        if ($comment->parentId === '' && $comment->level != 1) {
            return true;
        }

        // Is it set to be under a parent now, but didn't have one before?
        if ($comment->parentId !== '' && $comment->level == 1) {
            return true;
        }

        // Is the parentId set to a different comment ID than its previous parent?
        $criteria = craft()->elements->getCriteria('Comments_Comment');
        $criteria->ancestorOf = $comment;
        $criteria->ancestorDist = 1;
        $criteria->status = null;
        $criteria->localeEnabled = null;

        $oldParent = $criteria->first();
        $oldParentId = ($oldParent ? $oldParent->id : '');

        if ($comment->parentId != $oldParentId) {
            return true;
        }

        // Must be set to the same one then
        return false;
    }



    public function onBeforeSave(CommentsEvent $event)
    {
        $this->raiseEvent('onBeforeSave', $event);
    }

    // Checks is there are sufficient permissions for commenting on this element
    public function checkPermissions($element)
    {
        $settings = craft()->plugins->getPlugin('comments')->getSettings();
        $elementType = craft()->elements->getElementTypeById($element->id);
        
        // Do we even have any settings setup? By default - anything can be commented on
        if ($settings->permissions) {

            // Check for elementype-wide permissions - if turned off for entry, we don't show any new ones
            // But we still need to show comments for entries that have specifically been enabled on a per-element basis
            if (!array_key_exists($element->id, $settings->permissions[$elementType])) {
                if (!$settings->permissions[$elementType]['*']) {
                    return false;
                }
            } else {
                // Check for individual element permissions
                if (!$settings->permissions[$elementType][$element->id]) {
                    return false;
                }
            }
        }

        return true;
    }








}