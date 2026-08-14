import React, { useState } from 'react';
import { Star, ThumbsUp, ThumbsDown } from 'lucide-react';

type VoteControlProps = {
  contentId: string;
  initialRating?: number;
  initialVotes?: { up: number; down: number };
  onRate?: (score: number) => void;
  onVote?: (direction: 'up' | 'down') => void;
};

export const VoteControl: React.FC<VoteControlProps> = ({
  initialRating = 4.8,
  initialVotes = { up: 124, down: 5 },
  onRate,
  onVote,
}) => {
  const [userRating, setUserRating] = useState<number | null>(null);
  const [hoverRating, setHoverRating] = useState<number | null>(null);
  const [votes, setVotes] = useState(initialVotes);
  const [votedDirection, setVotedDirection] = useState<'up' | 'down' | null>(null);

  const handleStarClick = (score: number) => {
    setUserRating(score);
    if (onRate) onRate(score);
  };

  const handleVoteClick = (direction: 'up' | 'down') => {
    if (votedDirection === direction) return;
    setVotes((prev) => ({
      up: direction === 'up' ? prev.up + 1 : prev.up - (votedDirection === 'up' ? 1 : 0),
      down: direction === 'down' ? prev.down + 1 : prev.down - (votedDirection === 'down' ? 1 : 0),
    }));
    setVotedDirection(direction);
    if (onVote) onVote(direction);
  };

  return (
    <div className="flex flex-wrap items-center justify-between gap-4 p-4 bg-[var(--bg-card)] rounded-2xl border border-[var(--border-color)] shadow-sm transition-colors duration-300">
      {/* Star Rating System */}
      <div className="flex items-center gap-3">
        <div className="flex items-center gap-1">
          {[1, 2, 3, 4, 5].map((star) => (
            <button
              key={star}
              type="button"
              onMouseEnter={() => setHoverRating(star)}
              onMouseLeave={() => setHoverRating(null)}
              onClick={() => handleStarClick(star)}
              className="p-1 cursor-pointer transition-transform hover:scale-110"
            >
              <Star
                className={`w-5 h-5 ${
                  (hoverRating || userRating || Math.round(initialRating)) >= star
                    ? 'text-[var(--accent-color)] fill-current'
                    : 'text-[var(--border-color)]'
                }`}
              />
            </button>
          ))}
        </div>
        <span className="text-xs font-mono font-bold text-[var(--accent-color)]">
          {userRating ? `${userRating}.0 Verdin` : `${(initialRating ?? 0).toFixed(1)} Puan`}
        </span>
      </div>

      {/* Upvote / Downvote buttons */}
      <div className="flex items-center gap-2">
        <button
          onClick={() => handleVoteClick('up')}
          className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-mono font-semibold transition-colors cursor-pointer ${
            votedDirection === 'up'
              ? 'bg-[var(--accent-light)] border-[var(--accent-color)] text-[var(--accent-color)]'
              : 'bg-[var(--bg-tertiary)] border-[var(--border-color)] text-[var(--text-muted)] hover:text-[var(--text-primary)]'
          }`}
        >
          <ThumbsUp className="w-3.5 h-3.5" />
          <span>{votes.up}</span>
        </button>

        <button
          onClick={() => handleVoteClick('down')}
          className={`flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-mono font-semibold transition-colors cursor-pointer ${
            votedDirection === 'down'
              ? 'bg-rose-500/10 border-rose-500 text-rose-500'
              : 'bg-[var(--bg-tertiary)] border-[var(--border-color)] text-[var(--text-muted)] hover:text-[var(--text-primary)]'
          }`}
        >
          <ThumbsDown className="w-3.5 h-3.5" />
          <span>{votes.down}</span>
        </button>
      </div>
    </div>
  );
};
