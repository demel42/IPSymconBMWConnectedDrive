<?php
  
declare(strict_types=1);

trait BMWConnectedDriveImagesLib
{
    protected function GetBrandImage()
    {
        $img = 'iVBORw0KGgoAAAANSUhEUgAAAHgAAAB4CAYAAAA5ZDbSAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAyZpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNi1jMDY3IDc5LjE1Nzc0NywgMjAxNS8wMy8zMC0yMzo0MDo0MiAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvIiB4bWxuczp4bXBNTT0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wL21tLyIgeG1sbnM6c3RSZWY9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9zVHlwZS9SZXNvdXJjZVJlZiMiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENDIDIwMTUgKFdpbmRvd3MpIiB4bXBNTTpJbnN0YW5jZUlEPSJ4bXAuaWlkOkUzMUZBNTAyRkEzRjExRThBOTYyRjEyNDhDNUJFMjE0IiB4bXBNTTpEb2N1bWVudElEPSJ4bXAuZGlkOkUzMUZBNTAzRkEzRjExRThBOTYyRjEyNDhDNUJFMjE0Ij4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6RTMxRkE1MDBGQTNGMTFFOEE5NjJGMTI0OEM1QkUyMTQiIHN0UmVmOmRvY3VtZW50SUQ9InhtcC5kaWQ6RTMxRkE1MDFGQTNGMTFFOEE5NjJGMTI0OEM1QkUyMTQiLz4gPC9yZGY6RGVzY3JpcHRpb24+IDwvcmRmOlJERj4gPC94OnhtcG1ldGE+IDw/eHBhY2tldCBlbmQ9InIiPz6YCI7LAAA8IElEQVR42uxd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';
        return $img;
    }
}