<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RFIDScanned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tag;

    public function __construct($tag)
    {
        $this->tag = $tag;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('rfid-channel'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'rfid.scanned';
    }
}
