# Messaging data integrity

Conversation history is protected from accidental deletion. A property, guest, host, or message sender referenced by messaging records cannot be hard-deleted until the related history is handled explicitly. Deleting a conversation intentionally deletes its messages.

Future account-deletion and property-deletion features must define an explicit archive, retention, or conversation-deletion workflow before attempting a hard delete. Hiding or unpublishing a property is safe because it does not delete the property record or its conversation history.

Participant foreign keys must always be derived by application code. Request data must never be mass-assigned into `property_id`, `guest_id`, `host_id`, `conversation_id`, or `sender_id`.
