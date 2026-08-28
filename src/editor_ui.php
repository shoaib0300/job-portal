<?php

declare(strict_types=1);

/** Reorder controls for experience rows and resume sections. */
function editor_render_sort_controls(): void
{
    ?>
    <div class="section-sort-controls">
      <div class="sort-btn-group" role="group" aria-label="Reorder">
        <button type="button" class="sort-btn" data-move-up title="Move up" aria-label="Move up">
          <svg class="sort-btn-icon" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M8 4.5a.5.5 0 0 1 .354.146l4 4a.5.5 0 0 1-.708.708L8 5.707 4.354 9.354a.5.5 0 1 1-.708-.708l4-4A.5.5 0 0 1 8 4.5z"/>
          </svg>
        </button>
        <button type="button" class="sort-btn" data-move-down title="Move down" aria-label="Move down">
          <svg class="sort-btn-icon" viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M8 11.5a.5.5 0 0 1-.354-.146l-4-4a.5.5 0 0 1 .708-.708L8 10.293l3.646-3.647a.5.5 0 0 1 .708.708l-4 4A.5.5 0 0 1 8 11.5z"/>
          </svg>
        </button>
      </div>
      <span class="drag-hint" title="Drag to reorder" aria-hidden="true">
        <svg class="drag-hint-icon" viewBox="0 0 10 16" width="10" height="16" aria-hidden="true" focusable="false">
          <circle cx="3" cy="3" r="1.15" fill="currentColor"/>
          <circle cx="7" cy="3" r="1.15" fill="currentColor"/>
          <circle cx="3" cy="8" r="1.15" fill="currentColor"/>
          <circle cx="7" cy="8" r="1.15" fill="currentColor"/>
          <circle cx="3" cy="13" r="1.15" fill="currentColor"/>
          <circle cx="7" cy="13" r="1.15" fill="currentColor"/>
        </svg>
      </span>
    </div>
    <?php
}
