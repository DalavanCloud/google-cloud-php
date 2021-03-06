<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: google/cloud/speech/v1p1beta1/cloud_speech.proto

namespace Google\Cloud\Speech\V1p1beta1;

/**
 * The original media the speech was recorded on.
 *
 * Protobuf enum <code>Google\Cloud\Speech\V1p1beta1\RecognitionMetadata\OriginalMediaType</code>
 */
class RecognitionMetadata_OriginalMediaType
{
    /**
     * Unknown original media type.
     *
     * Generated from protobuf enum <code>ORIGINAL_MEDIA_TYPE_UNSPECIFIED = 0;</code>
     */
    const ORIGINAL_MEDIA_TYPE_UNSPECIFIED = 0;
    /**
     * The speech data is an audio recording.
     *
     * Generated from protobuf enum <code>AUDIO = 1;</code>
     */
    const AUDIO = 1;
    /**
     * The speech data originally recorded on a video.
     *
     * Generated from protobuf enum <code>VIDEO = 2;</code>
     */
    const VIDEO = 2;
}

