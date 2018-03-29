import _ from 'lodash'
import { dependenciesThreshold } from '../configuration/graph.js'

export class GraphConverter
{
    convertFromJson(report)
    {
        const couplings = this.retrieveBoundedContextCoupling(report)
        const boundedContexts = this.retrieveBoundedContextNodes(report, couplings)

        const data = {
            summary: {
                reportTime: new Date(report.summary.report_time),
            },
            nodes: Object.values(boundedContexts),
            edges: this.buildEdges(couplings, boundedContexts),
        }
        
        return data
    }
    
    retrieveBoundedContextNodes(report, couplings)
    {
        let countIn = this.countInDependencies(couplings)
        let countOut = this.countOutDependencies(couplings)
        
        let boundedContexts = []
        report.bounded_contexts.forEach((boundedContext, index) => {
            boundedContexts[boundedContext] = this.buildNode(index, boundedContext, countIn[boundedContext], countOut[boundedContext])
        })

        return boundedContexts
    }
    
    buildNode(index, boundedContext, countIn, countOut)
    {
        if (countOut === undefined)
        {
            countOut = 0
        }
        
        return {
            id: index,
            label: boundedContext,
            value: countIn === undefined ? 0 : countIn,
            color: this.computeColor(countOut)
        }
    }
    
    retrieveBoundedContextCoupling(report)
    {
        let couplings = {
            in: [],
            out: []
        }
        
        const defects = _.filter(report.defects, (defect) => ( defect.type === 'bc_coupling'))
        
        defects.forEach((defect) => {
            if(typeof couplings.in[defect.to] !== 'object')
            {
                couplings.in[defect.to] = []
            }
            couplings.in[defect.to].push(defect.from)

            if(typeof couplings.out[defect.from] !== 'object')
            {
                couplings.out[defect.from] = []
            }
            couplings.out[defect.from].push(defect.to)
        })
        
        return couplings
    }
    
    buildEdges(couplings, boundedContexts)
    {
        let edges = []
        _.keys(couplings.out).forEach((boundedContextFrom) => {
            const boundedContextTargets = _.countBy(couplings.out[boundedContextFrom])
            _.each(boundedContextTargets, (count, target) => {
                edges.push({
                    from: boundedContexts[boundedContextFrom].id,
                    to: boundedContexts[target].id,
                    value: count,
                    label: count.toString(),
                })
            })
        })
        
        return edges
    }
    
    countInDependencies(couplings)
    {
        return this.countDependencies(couplings.in)
    }

    countOutDependencies(couplings)
    {
        return this.countDependencies(couplings.out)
    }
    
    countDependencies(couplings)
    {
        let count = []
        _.keys(couplings).forEach((boundedContext) => {
            const boundedContextSources = _.countBy(couplings[boundedContext])
            count[boundedContext] = 0
            _.each(boundedContextSources, (value) => {
                count[boundedContext] += value
            })
        })
        
        return count
    }
    
    computeColor(count)
    {
        for(let i=0; i<dependenciesThreshold.length; i++)
        {
            if(count >= dependenciesThreshold[i].min && count <= dependenciesThreshold[i].max)
            {
                return dependenciesThreshold[i].color
            }
        }
    }
}
