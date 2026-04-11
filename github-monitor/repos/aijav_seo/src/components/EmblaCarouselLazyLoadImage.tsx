import React, { useState, useCallback } from 'react'

type PropType = {
  imgSrc: string
  inView: boolean
  alt?: string
  className?: string
  onError?: (e: React.SyntheticEvent<HTMLImageElement>) => void
}

export const LazyLoadImage: React.FC<PropType> = (props) => {
  const { imgSrc, inView, alt, className, onError } = props
  const [hasLoaded, setHasLoaded] = useState(false)

  const setLoaded = useCallback(() => {
    if (inView) setHasLoaded(true)
  }, [inView, setHasLoaded])

  return (
    <div className={`embla__lazy-load ${hasLoaded ? 'embla__lazy-load--has-loaded' : ''}`}>
      {!hasLoaded && <div className="embla__lazy-load__spinner" />}
      <img
        className={`embla__lazy-load__img ${className || ''}`}
        onLoad={setLoaded}
        onError={onError}
        loading="lazy"
        src={inView ? imgSrc : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'}
        alt={alt}
      />
    </div>
  )
}