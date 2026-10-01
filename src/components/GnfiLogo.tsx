import React from 'react';

export function GnfiLogo({ 
  className = "h-full w-auto", 
  isDarkMode = false 
}: { 
  className?: string;
  isDarkMode?: boolean;
}) {
  const logoSrc = isDarkMode ? '/icon/logow.png' : '/icon/logoh.png';
  const fallbackSrc = isDarkMode ? '/logow.png' : '/logoh.png';

  return (
    <img 
      src={logoSrc} 
      alt="Good News Nusantara" 
      className={`object-contain object-left ${className}`} 
      onError={(e) => {
        const target = e.target as HTMLImageElement;
        if (target.src !== window.location.origin + fallbackSrc) {
          target.src = fallbackSrc;
        }
      }}
    />
  );
}
