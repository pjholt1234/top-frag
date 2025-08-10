<?php

namespace App\Enums;

enum ProcessingStatus: string
{
    // Job Lifecycle Statuses
    case QUEUED = 'Queued';
    case VALIDATING = 'Validating';
    case UPLOADING = 'Uploading';
    case INITIALIZING = 'Initializing';
    case PARSING = 'Parsing';
    case PROCESSING_EVENTS = 'ProcessingEvents';
    case SENDING_METADATA = 'SendingMetadata';
    case SENDING_EVENTS = 'SendingEvents';
    case FINALIZING = 'Finalizing';
    case COMPLETED = 'Completed';
    case FAILED = 'Failed';

        // Error-Specific Statuses
    case VALIDATION_FAILED = 'ValidationFailed';
    case UPLOAD_FAILED = 'UploadFailed';
    case PARSE_FAILED = 'ParseFailed';
    case CALLBACK_FAILED = 'CallbackFailed';
    case TIMEOUT = 'Timeout';
    case CANCELLED = 'Cancelled';

        // Legacy statuses (keeping for backward compatibility)
    case PENDING = 'Pending';
    case PREPARING = 'Preparing';
    case PROCESSING = 'Processing';
}
