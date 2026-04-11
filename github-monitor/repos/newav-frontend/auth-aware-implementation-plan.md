# Authentication-Aware Content Refresh System Implementation Plan

## Overview

This document outlines the implementation plan for enhancing the InsAV application with an authentication-aware content refresh system. The system will automatically handle content updates when authentication state changes, eliminating stale data and improving user experience.

## Current State Analysis

### Existing Authentication Implementation
- Token stored in `localStorage` as "tokenNew"
- Reactive auth state via `useAuth` hook with custom events
- Axios interceptor automatically appends token to request body
- Token expiration handling with automatic logout

### Current React Query Usage
- **23 `useQuery` hooks** across the codebase
- **Only 2 hooks currently auth-aware**: `useVideoUrl`, `useUserInfo`
- **4 manual `invalidateQueries` calls** in auth components
- **5 components** currently import `useAuth`
- Limited error recovery with `refetch()` usage

### Identified Issues
- Inconsistent auth-aware patterns
- Manual query invalidation scattered across components
- Potential stale data when auth state changes
- No centralized auth-sensitive query management

## Implementation Plan

### Phase 1: Core Infrastructure (1-2 days)

#### 1.1 Create `useAuthAwareQuery` Hook
```typescript
// hooks/auth/useAuthAwareQuery.ts
export const useAuthAwareQuery = <TData>(
  baseKey: string[],
  queryFn: QueryFunction<TData>,
  options?: UseQueryOptions<TData>
) => {
  const { isAuthenticated } = useAuth();
  
  return useQuery({
    ...options,
    queryKey: [...baseKey, isAuthenticated ? 'authenticated' : 'anonymous'],
    queryFn,
    enabled: options?.enabled !== false,
  });
};
```

#### 1.2 Enhanced Authentication Context
```typescript
// contexts/AuthContext.tsx
interface AuthContextType {
  isAuthenticated: boolean;
  token: string | null;
  refreshAllQueries: () => Promise<void>;
  invalidateAuthSensitiveQueries: () => void;
}
```

#### 1.3 Global Authentication State Handler
```typescript
// hooks/auth/useAuthQueryRefresh.ts
export const useAuthQueryRefresh = () => {
  const queryClient = useQueryClient();
  const { isAuthenticated } = useAuth();
  const prevAuthState = useRef(isAuthenticated);

  useEffect(() => {
    if (prevAuthState.current !== isAuthenticated) {
      // Handle auth state change
      handleAuthStateChange();
      prevAuthState.current = isAuthenticated;
    }
  }, [isAuthenticated, queryClient]);
};
```

#### 1.4 Query Key Strategy
```typescript
// utils/queryKeys.ts
export const queryKeys = {
  videoInfo: (id: string, authState: boolean) => ['videoInfo', id, authState],
  videoUrl: (id: string, authState: boolean) => ['videoUrl', id, authState],
  userInfo: () => ['userInfo'],
  collections: (authState: boolean) => ['collections', authState],
} as const;
```

### Phase 2: Hook Migration (3-5 days)

#### 2.1 High Priority Hooks (Day 1-2)
**Critical auth-sensitive hooks requiring immediate attention:**

1. **useVideoUrl.ts** - Already partially auth-aware
   - Update to use new query key pattern
   - Remove manual auth checks in favor of centralized handling

2. **useUserInfo.ts** - Core user data
   - Enhance with better error handling
   - Integrate with global auth state management

3. **useVideoInfo.ts** - May return different data for authenticated users
   - Add auth-aware query key
   - Handle potential collect status differences

#### 2.2 Medium Priority Hooks (Day 3-4)
**Publisher/Actor related hooks (8 hooks):**
- usePublisherInfo.ts, useActorInfo.ts
- useMyPublisherList.ts, useSubscribedActorList.ts
- usePublisherList.ts, useActorList.ts
- useActorVideoList.ts, useActorListByPublisher.ts

#### 2.3 Low Priority Hooks (Day 5)
**Generic lists and categories (10 hooks):**
- useCategoryList.ts, useGroupList.ts
- useVideoList.ts, useVideoIndexList.ts
- useCategorizedVideoList.ts, useVideoHotList.ts
- useBanner.ts, useReviewList.ts
- useSelectedMonthVideoList.ts, useGlobalSearch.ts

### Phase 3: Component Updates (2-3 days)

#### 3.1 Authentication Components
**Files requiring updates:**
- `src/components/sign-in.tsx` - Simplify success handlers
- `src/components/sign-up.tsx` - Replace manual invalidation
- `src/pages/VideoPlayer.tsx` - Update collect handling

#### 3.2 List Components (6 files)
**Replace manual refetch patterns:**
- `src/components/free-list.tsx`
- `src/components/infinite-list.tsx`
- `src/components/my-actor-list.tsx`
- `src/components/my-publisher-list.tsx`
- `src/components/actor-list.tsx`
- `src/pages/Latest.tsx`

#### 3.3 Enhanced Component Pattern
```typescript
// Example: Enhanced component usage
export const VideoList = () => {
  const { data, isLoading, isError } = useAuthAwareVideoList();
  // Component automatically handles auth state changes
};
```

## Implementation Details

### Enhanced Hook Patterns

#### 1. Auth-Variant Query Hook
```typescript
export const useAuthVariantQuery = <TData>(
  endpoint: string,
  params: any,
  queryFn: QueryFunction<TData>
) => {
  const { isAuthenticated } = useAuth();
  
  return useQuery({
    queryKey: [endpoint, params, isAuthenticated ? 'auth' : 'public'],
    queryFn,
    refetchOnMount: true, // Force refetch when auth state changes
  });
};
```

#### 2. Smart Cache Strategy
```typescript
// Different cache times based on auth state
const getCacheTime = (isAuthenticated: boolean) => ({
  staleTime: isAuthenticated ? 5 * 60 * 1000 : 30 * 60 * 1000,
  cacheTime: isAuthenticated ? 10 * 60 * 1000 : 60 * 60 * 1000,
});
```

#### 3. Selective Query Invalidation
```typescript
const publicEndpointsWithAuthVariations = [
  'videoInfo',
  'publisherInfo', 
  'actorInfo',
  'categoryList'
];

const handleAuthStateChange = () => {
  // Invalidate auth-sensitive queries
  queryClient.invalidateQueries({
    predicate: (query) => 
      publicEndpointsWithAuthVariations.some(endpoint =>
        query.queryKey.includes(endpoint)
      )
  });
};
```

## Risk Mitigation

### Breaking Changes Prevention
- Maintain backward compatibility during migration
- Gradual rollout with feature flags
- Comprehensive testing before each phase

### Performance Monitoring
- Monitor React Query cache size
- Track API call frequency
- Measure page load times

### Testing Strategy
- Unit tests for new hooks
- Integration tests for auth state changes
- E2E tests for critical user flows

## Success Metrics

### Performance Improvements
- **30-50% reduction** in unnecessary API calls
- **20-40% fewer** duplicate requests after auth changes
- **Sub-3 second** page load times maintained

### Developer Experience
- **50% reduction** in auth-related bugs
- **Faster feature development** velocity
- **Consistent patterns** across codebase

### User Experience
- **Automatic content sync** after login/logout
- **No stale data** visibility
- **Improved app responsiveness**

## Timeline

| Phase | Duration | Dependencies | Deliverables |
|-------|----------|--------------|--------------|
| Phase 1 | 1-2 days | None | Core infrastructure |
| Phase 2 | 3-5 days | Phase 1 | Migrated hooks |
| Phase 3 | 2-3 days | Phase 2 | Updated components |
| **Total** | **6-10 days** | - | **Complete system** |

## Files to Create/Modify

### New Files
- `src/hooks/auth/useAuthAwareQuery.ts`
- `src/hooks/auth/useAuthQueryRefresh.ts`
- `src/utils/queryKeys.ts`

### Modified Files
**High Priority (3 files):**
- `src/hooks/video/useVideoUrl.ts`
- `src/hooks/user/useUserInfo.ts`
- `src/hooks/video/useVideoInfo.ts`

**Medium Priority (8 files):**
- Publisher/Actor related hooks

**Low Priority (10 files):**
- Generic list and category hooks

**Components (10 files):**
- Authentication and list components

## Implementation Notes

### Current Auth-Aware Patterns
```typescript
// useVideoUrl.ts - Already includes auth in query key
queryKey: ["videoUrl", videoId, isAuthenticated]

// useUserInfo.ts - Properly gated by auth state
enabled: isAuthenticated
```

### Manual Invalidation Patterns to Replace
```typescript
// sign-up.tsx & sign-in.tsx - Replace with centralized handling
await queryClient.invalidateQueries({ queryKey: ["userInfo"] });
queryClient.invalidateQueries({ queryKey: ["videoUrl"] });
```

### Refetch Patterns to Enhance
```typescript
// Multiple components - Replace with auth-aware queries
const { refetch } = useVideoList();
onSuccess: () => refetch()
```

## Next Steps

1. **Setup Phase 1** infrastructure
2. **Test** with critical hooks (useVideoUrl, useUserInfo)
3. **Gradual migration** of remaining hooks
4. **Component updates** and testing
5. **Performance monitoring** and optimization

---

**Status**: Planning Complete - Ready for Implementation
**Estimated Effort**: 6-10 days
**Risk Level**: Low-Medium
**Expected ROI**: High